<?php

namespace Drupal\qwizard;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Session\AccountProxy;
use Drupal\qwizard\Entity\Qwiz;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\qwizard\Entity\QwizSnapshot;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\UserInterface;
use function JmesPath\search;

/**
 * Class MergedQwiz.
 */
class MergedQwiz implements MergedQwizInterface {

  protected $qwiz;

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new MergedQwiz object.
   */
  public function __construct() {

  }

  public function setQwiz(Qwiz $qwiz){
    $this->qwiz = $qwiz;
  }

  public function setUser($currentUser){
    $this->currentUser = $currentUser;
  }

  /**
   * Determines if this is a merged qwiz.
   *
   * @return bool
   */
  public function isMergedQwiz() {
    // Check if $this->qwiz has field_merged_qwiz true.
    return !empty($this->qwiz->field_merged_quiz->value);
  }

  /**
   * Returns the component qwiz results.
   *
   * Need all the results from the random quiz questions that have the same
   * topics as the component quiz.
   *
   * @param $result_qwizzes
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getIndividualResults($result_qwizzes, $subscription_id) {
    $question_storage = \Drupal::entityTypeManager()->getStorage('node');
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    // Get topic from $result_qwizzes.
    $result_qwiz_topics = [];
    foreach ($result_qwizzes as  $result_qwiz) {
      $result_qwiz_topics = array_merge($result_qwiz_topics, $result_qwiz->getTopicIds());
    }
    $result_qwiz_topics = array_unique($result_qwiz_topics);

    // Get all results from $this->qwiz.
    // Get current sub.
    $course = $this->qwiz->getCourse();
    $subscription = \Drupal::service('qwsubs.subscription_handler')->getCurrentSubscription($course, $this->currentUser->id());
    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('user_id', $this->currentUser->id())
      ->condition('subscription_id', $subscription_id)
      ->condition('qwiz_id', $this->qwiz->id());
    // @todo: filter by date.
    $merged_quiz_results_ids = $query->execute();
    $qwiz_result_storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $topic_results = [];
    $component_topics = $this->getComponentQwizTopics();
    $merged_quiz_results = [];
    if(!empty($merged_quiz_results_ids)) {
      $merged_quiz_results = $qwiz_result_storage->loadMultiple($merged_quiz_results_ids);
    }


    // pre-loading all nodes and snapshots we'll need
    // VID doesn't have static caching for the request for drupal like NID does, so returning it and sending it across
    $preload_data = $this->preloadMultipleSnapshotArraysFromQuizResults($merged_quiz_results);
    $questions_from_vids = $preload_data['questions_from_vids'];


    // Loading all topics at once instead of 1 by 1 later
    $topics_by_id = $term_storage->loadByProperties(['vid' => 'topics']);

    $snapshots_questions_by_QR_id = [];
    $questions_ids_to_load = [];
    foreach ($merged_quiz_results as $qwizResult) {
      // Load the qwizResult.
      // Loop through qwiz result questions and match to topics.
      $snapshot = $qwizResult->getSnapshot();
      $snapshot->setPreloadedQuestionsByVID($questions_from_vids);
      $snapshot_array = $snapshot->getSnapshotArray();
      if (array_key_exists('questions', $snapshot_array)) {
        $questions = $snapshot_array['questions'];
        $snapshots_questions_by_QR_id[$qwizResult->id()] = $snapshot_array;
        foreach($questions as $question_data){
          $id = $question_data['question_id'];
          $questions_ids_to_load[$id] = $id;
        }
      }
    }
    $questions_by_id = $question_storage->loadMultiple($questions_ids_to_load);
    foreach ($merged_quiz_results as $qwizResult) {
      // Load the qwizResult.
      // Loop through qwiz result questions and match to topics.
      if(empty($snapshots_questions_by_QR_id[$qwizResult->id()])){
        // If the qwiz result has no snapshot, just skip this result. QR ID 7208435  is a known example of this
        continue;
      }
      $snapshot = $snapshots_questions_by_QR_id[$qwizResult->id()];
      if(array_key_exists('questions', $snapshot)) {
        foreach ($snapshot['questions'] as $question_data) {
          // Load question and check topic against qwiz topic.
          // @todo use paragraphs to get topics instead
          // @todo could probably use results JSON
          $question = $questions_by_id[$question_data['question_id']];
          $q_topics = [];
          if(!empty($question->field_topics)) {
            foreach ($question->field_topics as $value) {
              $q_topics[] = $value->target_id;
            }
          }
          $matching_topics = fasterArrayIntersect($component_topics, $q_topics, $result_qwiz_topics);
          if (!empty($matching_topics)) {
            // Loop through topics and tally results.
            foreach ($matching_topics as $topic_id) {
              $topicTerm = $topics_by_id[$topic_id];
              $term_name = $topicTerm->getName();
              // Get results already tallied.
              if (!isset($topic_results[$term_name]['correct'])) $topic_results[$term_name]['correct'] = 0;
              if (!isset($topic_results[$term_name]['attempted'])) $topic_results[$term_name]['attempted'] = 0;
              if (!isset($topic_results[$term_name]['seen'])) $topic_results[$term_name]['seen'] = 0;
              if ($question_data['chosen_answer'] == $question_data['correct_answer']) {
                ++$topic_results[$term_name]['correct'];
              }
              if (!empty($question_data['chosen_answer'])) {
                ++$topic_results[$term_name]['attempted'];
              }
              ++$topic_results[$term_name]['seen'];
            }
          }
        }
      }
    }

    return $topic_results;
  }


  /**
   * Used to run loadMultiple on multiple snapshots at once first
   * Used primarily by MergedQuiz, to warm up loadMultiple cache during 100's of calls to getSnapshotArray
   * Returns snapshots by VID since those don't get cached like NID version does
   */
  public function preloadMultipleSnapshotArraysFromQuizResults($qwiz_results){
    $snapshots_to_preload = [];
    $question_vids_to_load = [];
    $question_nids_to_load = [];
    $questions_from_nids = [];
    $QWGeneral = \Drupal::service('qwizard.general');
    $enable_snapshots_from_vid = $QWGeneral->getStatics()['enable_snapshots_from_vid'];


    foreach($qwiz_results as $result){
      $snapshot_id = $result->getSnapshotId();
      $snapshots_to_preload[$snapshot_id] = $snapshot_id;
    }

    $snapshot_storage = \Drupal::entityTypeManager()->getStorage('qwiz_snapshot');
    $snapshots = $snapshot_storage->loadMultiple($snapshots_to_preload);
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    foreach($snapshots as $snapshot_entity) {
      $snapshot = $snapshot_entity->snapshot->value;
      if (empty($snapshot)) {
        continue;
      }
      $ss_array = Json::decode($snapshot);
      // Add in the question text, feedback and answer text.
      $ss_questions = !empty($ss_array['questions']) ? $ss_array['questions'] : [];


      foreach ($ss_questions as $idx => $ss_question) {
        $vid = $ss_question['question_vid'];
        $nid = $ss_question['question_id'];
        $question_vids_to_load[$vid] = $vid;
        $question_nids_to_load[$nid] = $nid;
      }
    }

    $questions_from_vids = [];
    if($enable_snapshots_from_vid) {
      $questions_from_vids = $node_storage->loadMultipleRevisions($question_vids_to_load);
      foreach ($questions_from_vids as $question) {
        $nid = $question->id();
        $questions_from_nids[$nid] = $question;
        if (!empty($question_nids_to_load[$nid])) {
          unset($question_nids_to_load[$nid]);
        }
      }
    }
    if (!empty($question_nids_to_load)) {
      // Not returning is fine here, is to warm up the node load cache later
      $questions_from_nids = array_merge($node_storage->loadMultiple($question_nids_to_load), $questions_from_nids);
    }

    return ['questions_from_vids' => $questions_from_vids, 'snapshots_by_id' => $snapshots];
  }

  /**
   * Returns the component quiz topics.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getComponentQwizTopics() {
    $QwCache = \Drupal::service('qwizard.cache');
    $topics = $this->qwiz->getTopicIds();
    $class_id = $this->qwiz->getClassId();
    $cache_key = 'getComponentQwizTopics_'.$class_id.'_'.json_encode($topics);
    $cache = $QwCache->checkCache($cache_key, true);
    #$cache = null;
    $component_topics = [];
    if(!empty($cache)){
      $component_topics = $cache;
    }else {
      $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
      // Get all topics in this qwiz.

      // Init return array.

      // We only want "child" quizzes of the merged quiz.
      // Must match class & topic.
      $orGroup = \Drupal::entityQuery('qwiz')->orConditionGroup()
        ->notExists('field_merged_quiz')
        ->condition('field_merged_quiz', TRUE, "!=");
      $query = \Drupal::entityQuery('qwiz')
        ->condition('class', $class_id)
        ->condition('topics', $topics, 'IN')
        ->condition($orGroup);
      $qids = $query->execute();
      $component_qwizzes = $qwiz_storage->loadMultiple($qids);
      foreach ($component_qwizzes as $qwiz) {
        $qwiz_topics = $qwiz->getTopicIds();
        $component_topics = array_merge($component_topics, $qwiz_topics);
      }

      // Set cache
      $QwCache->setCacheFile($cache_key, $component_topics, true);
    }
    return $component_topics;
  }

  /**
   * Returns the quiz object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\qwizard\Entity\Qwiz|mixed|null
   */
  public function getQuiz() {
    return $this->qwiz;
  }

  /**
   * Returns a list of merged quizzes the given quiz belongs to.
   *
   * @param $qwiz
   *
   * @return array Of Qwiz objects.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function mergedQuizzesWithSharedTopic($qwiz) {
    $QwCache = \Drupal::service('qwizard.cache');
    $class_id = $qwiz->getClassId();
    $cache_key = 'mergedQuizzesWithSharedTopic_'.$class_id;
    $cache = $QwCache->checkCache($cache_key);
    $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    #$cache = null;
    $merged_quiz_array = [];

    if(!empty($cache)){
      $merged_quiz_array = $cache;
    }else {
      // Query merged quizzes and check to see if the given qwiz has common topics
      // with in this class.
      $merged_quiz_array = [];
      $query = \Drupal::entityQuery('qwiz')
        ->condition('class', $qwiz->getClassId())
        ->condition('field_merged_quiz', TRUE);
      $qids = $query->execute();
      $merged_qwizzes = $qwiz_storage->loadMultiple($qids);
      $topics = $qwiz->getTopicIds();
      foreach ($merged_qwizzes as $merged_qwiz) {
        $merged_qwiz_topics = $merged_qwiz->getTopicIds();
        $shared_topics = fasterArrayIntersect($topics, $merged_qwiz_topics);
        if (!empty($shared_topics)) {
          $merged_quiz_array[$merged_qwiz->id()] = $merged_qwiz->id();
        }
      }

      // Set cache
      $QwCache->setCacheFile($cache_key, $merged_quiz_array, true);
    }
    $merged_quiz_array = $qwiz_storage->loadMultiple($merged_quiz_array);

    return $merged_quiz_array;
  }

}
