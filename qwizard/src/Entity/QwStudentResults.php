<?php

namespace Drupal\qwizard\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\qwsubs\Entity\Subscription;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserInterface;
use Exception;

/**
 * Defines the Student Results entity.
 *
 * @ingroup qwizard
 *
 * @ContentEntityType(
 *   id = "qw_student_results",
 *   label = @Translation("Student Results"),
 *   bundle_label = @Translation("Student Results type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwStudentResultsListBuilder",
 *     "views_data" = "Drupal\qwizard\Entity\QwStudentResultsViewsData",
 *     "translation" = "Drupal\qwizard\QwStudentResultsTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\qwizard\Form\QwStudentResultsForm",
 *       "add" = "Drupal\qwizard\Form\QwStudentResultsForm",
 *       "edit" = "Drupal\qwizard\Form\QwStudentResultsForm",
 *       "delete" = "Drupal\qwizard\Form\QwStudentResultsDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwStudentResultsHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\qwizard\QwStudentResultsAccessControlHandler",
 *   },
 *   base_table = "qw_student_results",
 *   data_table = "qw_student_results_field_data",
 *   translatable = TRUE,
 *   permission_granularity = "bundle",
 *   admin_permission = "administer student results entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" =
 *     "/admin/qwizard/structure/qw_student_results/{qw_student_results}",
 *     "add-page" = "/admin/qwizard/structure/qw_student_results/add",
 *     "add-form" =
 *     "/admin/qwizard/structure/qw_student_results/add/{qw_student_results_type}",
 *     "edit-form" =
 *     "/admin/qwizard/structure/qw_student_results/{qw_student_results}/edit",
 *     "delete-form" =
 *     "/admin/qwizard/structure/qw_student_results/{qw_student_results}/delete",
 *     "collection" = "/admin/qwizard/structure/qw_student_results",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   bundle_entity_type = "qw_student_results_type",
 *   field_ui_base_route = "entity.qw_student_results_type.edit_form"
 * )
 */
class QwStudentResults extends ContentEntityBase implements QwStudentResultsInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  protected array $results_to_rebuild_with = [];

  protected bool $results_to_rebuild_set = FALSE;

  public $only_rebuild_with_set_results = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    parent::preCreate($storage, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * Gets the Quiz Results json.
   *
   *   Results as json.
   */
  public function getSubscriptionId() {
    return $this->get('subscription_id')->target_id;
  }

  /**
   * Sets the Quiz Results.
   *
   * @param $subscription_id
   */
  public function setSubscriptionId($subscription_id) {
    if ($subscription_id instanceof Subscription) {
      $subscription_id = $subscription_id->id();
    }
    $this->set('subscription_id', $subscription_id);
  }

  /**
   * Gets the Quiz Results json.
   *
   *   Results as json.
   */
  public function getCourseId() {
    return $this->get('course')->target_id;
  }

  /**
   * Sets the Quiz Results.
   *
   * @param $course
   */
  public function setCourse($course) {
    if ($course instanceof Term) {
      $course = $course->id();
    }
    $this->set('course', $course);
  }

  /**
   * Gets Class ID
   *
   */
  public function getClassId() {
    return $this->get('class')->target_id;
  }

  /**
   * Gets Class Entity
   *
   */
  public function getClass() {
    return $this->get('class')->entity;
  }

  /**
   * Sets the Quiz Results.
   *
   * @param $class
   */
  public function setClass($class) {
    if ($class instanceof Term) {
      $class = $class->id();
    }
    $this->set('class', $class);
  }

  public function getQwiz() {
    $course_id = $this->getCourseId();
    $class_id = $this->getClassId();
    $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    $qwiz = $qwiz_storage->loadByProperties([
      'class' => $class_id,
      'course' => $course_id,
    ]);

    return reset($qwiz);
  }

  /**
   * Gets the Quiz Results json data.
   * Defaults to array, but 'string' can be specified for raw JSON
   *
   * @throws Exception
   */
  public function getResultsJson($type = 'array') {
    $results = $this->get('results')->value;
    if ($type == 'array' || $type == 'translated_array' || $type == 'array_keyed_by_qwiz_id') {
      $results = json_decode($results, TRUE);
    }
    elseif ($type != 'string') {
      throw new Exception('getResultsJson requires either "array" or "string" as parameters');
    }

    if ($type == 'array_keyed_by_qwiz_id' && !empty($results)) {
      $new_results = [];
      foreach ($results as $key => $result) {
        if (empty($result['id'])) {
          continue;
        }
        $new_label_parts = explode('-', $result['label']);
        if (count($new_label_parts) > 1) {
          array_pop($new_label_parts);
        }
        $new_label = implode('-', $new_label_parts);
        $result['label'] = $new_label;

        $new_results[$result['id']] = $result;
      }

      $results = $new_results;
    }
    if ($type == 'translated_array' && !empty($results)) {
      // Order the results
      /*$class_id = $this->getClassId();
      if($class_id == 185 || $class_id == 460) {
        if(!empty($results['Total'])) {
          $orderLabelList = [
            "Total",
            "Canine",
            "Feline",
            "Bovine",
            "Equine",
            "Porcine",
            "Small Ruminants",
            "Exotics",
            "Poultry",
            "Cross Species",
          ];
          $ordered_results = [];
          foreach ($orderLabelList as $name) {
            $ordered_results[$name] = $results[$name];
          }
          $results = $ordered_results;
        }
      }*/

      // get translated names
      foreach ($results as $key => $value) {
        if (!empty($value['label']) && $value['id'] != 'total') {
          $results[$key]['label'] = t($value['label']);
        }
      }
    }

    return $results;
  }

  /**
   * Sets the Quiz Results.
   *
   * @param string|array $json
   *   The Quiz Results json string or array.
   */
  public function setResults($json) {
    if (is_array($json)) {
      $json = json_encode($json);
    }
    $this->set('results', $json);
  }

  /**
   * Sets results_to_rebuild_with.
   *
   * @param $results
   *
   * @return void
   */
  public function setResultsToRebuildWith($results) {
    $this->results_to_rebuild_with = $results;
    $this->results_to_rebuild_set = TRUE;
  }

  /**
   * Gets results_to_rebuild_with.
   *
   * @return array
   */
  public function getResultsToRebuildWith(): array {
    return $this->results_to_rebuild_with;
  }

  /**
   * Rebuilds student results for a single result entity
   * To debug, trigger this from "Membership Manager" UI, or
   * /api-v1/student-results?_format=json&user_id=58125&course=200&force_fresh_data=1
   */
  public function rebuildResults() {
    $uid = $this->getOwnerId();
    $sub_id = $this->getSubscriptionId();
    $course_id = $this->getCourseId();
    $class_id = $this->getClassId();
    $QwizardGeneral = \Drupal::service('qwizard.general');

    $class_type = $QwizardGeneral->getClassType($class_id, $course_id);

    // Can be used for quicker debugging on a $class_type
    // should be commented in commits
    //if($class_type != 'study_mode') return [];
    //if($class_type != 'test_mode') return [];
    //if($class_type != 'college_study') return [];

    $results = [];
    if ($class_type == 'empty') {
      // This code disables calculating the active result where $class_id is null. Nothing is using it currently.
      return [];
    }
    elseif ($class_type == 'study_mode') {
      //$results = $this->tallyClassResults();
      $results = $this->getTestModeResults();
    }
    elseif ($class_type == 'test_mode') {
      $results = $this->getTestModeResults();
    }
    else {
      $quiz_results = self::getResultsToRebuildWith();
      if (!$this->results_to_rebuild_set) {
        // Results were not loaded ahead of time, just load them here
        $qwizResultsStorage = \Drupal::entityTypeManager()
          ->getStorage('qwiz_result');
        $query = \Drupal::entityQuery('qwiz_result')
          ->condition('user_id', $uid)
          ->condition('subscription_id', $sub_id)
          ->condition('course', $course_id)
          ->sort('id', 'desc');
        $query->condition('class', $class_id);
        $qr_ids = $query->execute();
        $quiz_results = [];
        if (!empty($qr_ids)) {
          $quiz_results = $qwizResultsStorage->loadMultiple($qr_ids);
        }
      }
      if ($class_type == 'secondary_study' || $class_type == 'college_study') {
        //$results = $this->tallyClassResults();
        $this->setResultsToRebuildWith($quiz_results);
        $this->only_rebuild_with_set_results = 1;
        $results = $this->getTestModeResults();
      }
      else {
        // Get the results
        $results = $this->scoreStudentResults($quiz_results, NULL, NULL, NULL);

        // We need to put a count on each
        $params = ['course_id' => $course_id, 'class' => $class_id];
        $QwizardGeneral = \Drupal::service('qwizard.general');
        if (!empty($results)) {
          foreach ($results as $key => $quiz_result) {
            // WRONG??
            $quiz_result_quiz = $quiz_result['id'];
            $topic_ids = array_keys($QwizardGeneral->getTopicsInQwiz($quiz_result_quiz));
            if (!empty($topic_ids)) {
              $params['topics'] = $topic_ids;
            }
            $secondary_total = count($QwizardGeneral->getTotalQuizzes($params));
            if (empty($secondary_total)) {
              #dpm($params);
              #dpm('this should not be empty. set results should be in class');
            }

            $results[$key]['total_questions'] = $secondary_total;
            $results[$key]['score_all'] = $secondary_total ? $results[$key]['correct'] / $secondary_total : 0;
          }
        }
      }
    }

    // Set the results into the results array, if results were able to load
    if (!empty($results)) {
      $this->setResults($results);
    }

    #dpm($results);
    return $results;
  }

  /**
   *
   * @param $multiquiz_results
   * @param $course_id
   * @param $class_id
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTestModeResults(): array {
    $course_id = $this->getCourseId();
    $class_id = $this->getClassId();
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $QwCache = \Drupal::service('qwizard.cache');
    $question_storage = \Drupal::entityTypeManager()->getStorage('node');
    $results_storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $classType = $QwizardGeneral->getClassType($class_id, $course_id);

    /*$pool_qwiz = $pool->getQuiz();
    $pool_data['qwiz_id'] = $pool_qwiz->id();
    var_dump($pool_data); exit;
    $pool_data['type'] = $pool_qwiz->getQwPoolDecrSettings();*/

    $statics = $QwizardGeneral->getStatics();
    $multiquiz_quizzes = $statics['multiquiz_quizzes'];
    $banlist_topics = $statics['banlist_topics'];
    $qwiz_id = $multiquiz_quizzes[$course_id];

    $totals_per_question = ['seen' => [], 'attempted' => [], 'correct' => []];

    // Get all question ids for this class.
    $params = ['course_id' => $course_id, 'class' => $class_id];
    $all_question_nids = $QwizardGeneral->getTotalQuizzes($params);

    // Build out initial data for topic, including total per topic
    $quiz_cache_key = 'quizResultsCache_' . $course_id . '_' . $class_id;
    $cache = $QwCache->checkCache($quiz_cache_key);

    // DONT LEAVE IN
    //$cache = null;
    if (empty($cache['topic_results']) || empty ($cache['questions_with_topics'])) {
      $cache = $QwCache->buildClassCache($course_id, $class_id);
    }

    if (empty($cache['topic_results']) || empty ($cache['questions_with_topics'])) {
      throw new \Exception('Error with QwCache, unable to load topics for course ' . $course_id . ' class ' . $class_id . '. JSON returned ' . json_encode($cache));
    }

    $topic_results = $cache['topic_results'];
    $questions_with_topics = $cache['questions_with_topics'];

    // Load pool for checking correct results as a backup
    $pool_data = ['correct' => [], 'incorrect' => []];
    if (!$this->only_rebuild_with_set_results) {
      $pool = QwPool::getPoolForClass($class_id, $this->getOwnerId(), $this->getSubscriptionId());
      if (!empty($pool)) {
        $pool_questions = json_decode($pool->get('questions')
          ->getValue()[0]['value'], TRUE);
        unset($pool_questions['complete']);
        if (!empty($pool_questions)) {
          if (!empty($pool_questions['correct'])) {
            foreach ($pool_questions['correct'] as $question) {
              $pool_data['correct'][$question] = $question;
            }
            unset($pool_questions['correct']);
          }
          // Add the rest of pool data to incorrect
          foreach ($pool_questions as $label => $pool_question_collection) {
            foreach ($pool_question_collection as $question) {
              $pool_data['incorrect'][$question] = $question;
            }
          }
        }
      }
    }

    // Load & Decode all snapshots at once
    $snapshots = $this->getSnapshotResults();

    foreach ($snapshots as $qr_id => $snapshot_array) {
      if (!empty($snapshot_array) && !empty($snapshot_array['questions'])) {
        foreach ($snapshot_array['questions'] as $question_data) {
          // If this happens, snapshot array is heavily damaged. Can only ignore it here.
          if (empty($question_data['question_id'])) {
            continue;
          }

          $question_id = $question_data['question_id'];

          if (!in_array($question_id, $all_question_nids)) {
            // The question is no longer in the active set of questions. Don't count it from snapshots
            continue 1;
          }

          $is_correct = $this->isSnapshotQuestionCorrect($question_data, $question_id, $all_question_nids, $pool_data);

          // Add to $topic_results array for every topic this question appears in
          if (!empty($questions_with_topics[$question_id])) {
            foreach ($questions_with_topics[$question_id] as $question_topic_id) {

              // ZUKU-1413 - Test mode results were getting doubled due to random being on everything, this stops that
              // Will still get included if random is the only category
              if (count($questions_with_topics[$question_id]) > 1 && in_array($question_topic_id, $banlist_topics)) {
                continue;
              }

              $totals_per_question['seen'][] = $question_id;
              $topic_results[$question_topic_id]['seen']++;

              // Only count the attempt if they actually chose an answer
              if (!empty($question_data['chosen_answer'])) {
                $topic_results[$question_topic_id]['attempted']++;
                $totals_per_question['attempted'][] = $question_id;
              }

              // Don't double count correct answers
              if (!empty($topic_results[$question_topic_id]['correct_questions'][$question_id])) {
                continue;
              }

              // append to result data, as well as totals
              if (empty($totals_per_question['correct'][$question_id])) {
                if ($is_correct) {
                  $totals_per_question['correct'][$question_id] = 1;
                  $topic_results[$question_topic_id]['correct']++;
                }
              }
              else {
                //Question is in big array already. Make it correct if needed
                if ($is_correct) {
                  $totals_per_question['correct'][$question_id]++;
                  $topic_results[$question_topic_id]['correct']++;
                }
              }

              if ($is_correct) {
                $topic_results[$question_topic_id]['correct_questions'][$question_id] = 1;
              }
            }
          }
        }
      }
    }

    // We want random for correct questions, but we don't want to include it in results.
    if (!empty($topic_results[224])) {
      unset($topic_results[224]);
    }

    // Set total questions in $topic_results.
    $new_results = [];
    $totals = [
      'label' => 'Total',
      'total_questions' => count($all_question_nids),
      'seen' => count($totals_per_question['seen']),
      'attempted' => count($totals_per_question['attempted']),
      'correct' => count($totals_per_question['correct']),
      'name' => 'Total',
    ];

    // @todo: figure out why these conditions sometimes exists and fix it right.
    /*if ($totals['attempted'] > $totals['total_questions']) {
      $totals['attempted'] = $totals['total_questions'];
    }*/
    if ($totals['seen'] > $totals['total_questions']) {
      $totals['seen'] = $totals['total_questions'];
    }
    if ($totals['correct'] > $totals['total_questions']) {
      $totals['correct'] = $totals['total_questions'];
    }

    $new_results['total'] = $results_storage->create($totals);

    // Sorting
    $topic_results = $this->sortTopics($topic_results, $course_id);

    // Turn results into entities for scoreStudentResults() to process
    // Takes 0.02 seconds
    foreach ($topic_results as $question_topic_id => $topic_result) {
      $new_results[$question_topic_id] = $results_storage->create($topic_result);
    }

    // Score each group of topics
    $multiquiz_data = [];
    foreach ($new_results as $topic_id => $topic_all_results) {
      $topic_name = $topic_all_results->getName();

      // College study includes a bunch of topics with 0 total questions. This is fine for other classes but remove them here
      if ($classType == 'college_study') {
        if (empty($topic_all_results->getTotalQuestions())) {
          continue;
        }
      }

      $row = $this->scoreStudentResults([$topic_all_results], $topic_id, $topic_name);
      if (!empty($row[$topic_name])) {
        $multiquiz_data[$topic_name] = $row[$topic_name];
      }
    }

    return $multiquiz_data;
  }

  public function sortTopics($topic_results, $course_id) {
    // Sort alphabetically
    uasort($topic_results, function ($a, $b) {
      return strcmp($a['label'], $b['label']);
    });

    // Custom sorting for NAVLE
    // Force the order of the Practice test categories for NAVLE in the breakdown tables:
    // Dog, Cat, Cow, Horse, Pig, Small Rum, Exo, Poultry, Cross Species
    if ($course_id == 200) {
      $sorted_topic_results = [];
      // Canine
      $topic_id = 215;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Feline
      $topic_id = 216;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Bovine
      $topic_id = 218;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Equine
      $topic_id = 217;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Porcine
      $topic_id = 220;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Small Ruminants
      $topic_id = 222;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Exotics
      $topic_id = 221;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Poultry
      $topic_id = 223;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      // Cross Species
      $topic_id = 219;
      $sorted_topic_results[$topic_id] = $topic_results[$topic_id];

      $topic_results = $sorted_topic_results;
    }

    return $topic_results;
  }

  protected function getSnapshotResults() {
    $con = Database::getConnection('default', 'default');
    $query = $con->select('qwiz_result', 'qr');
    $query->fields('snapshot', ['snapshot']);
    $query->fields('qr', ['id']);
    $query->condition('qr.user_id', $this->getOwnerId());
    $query->condition('qr.subscription_id', $this->getSubscriptionId());
    $query->condition('qr.class', $this->getClassId(), '=');

    if ($this->only_rebuild_with_set_results) {
      // @todo This is causing error on user purchase, check why
      //   only_rebuild_with_set_results is set when there are no results.
      $rebuild_results = $this->getResultsToRebuildWith();
      if (!empty($rebuild_results)) {
        $query->condition('qr.id', array_keys($rebuild_results), 'IN');
      }
    }
    $query->leftJoin('qwiz_snapshot', 'snapshot', 'qr.snapshot = snapshot.id');
    $query_data = $query->execute()->fetchAll(\PDO::FETCH_OBJ);

    $snapshot_json_by_id = [];
    foreach ($query_data as $snapshot) {
      $snapshot_json_by_id[$snapshot->id] = json_decode($snapshot->snapshot, TRUE);
    }

    return $snapshot_json_by_id;
  }

  /**
   * Returns the class results by using entityQueryAggregate
   * Works great for study_mode
   * High performance. .3 seconds at time of writing. Can be improved but not
   * necessary at all
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function tallyClassResults(): array {
    $ClassesHandler = \Drupal::service('qwizard.classeshandler');
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $results_storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');

    $statics = $QwizardGeneral->getStatics();
    $banlist_topic_names = $statics['banlist_topic_names'];

    // Get for each qwiz topic in a class
    $class_quizzes = $ClassesHandler->getQwizzesInClass($this->getClass(), TRUE);

    // Sort alphabetically
    uasort($class_quizzes, function ($a, $b) {
      return strcmp($a->label(), $b->label());
    });

    $uid = $this->getOwnerId();
    $sub_id = $this->getSubscriptionId();
    $course_id = $this->getCourseId();
    $class_id = $this->getClassId();
    $total_label = 'Total';

    // Get Total
    // This version was giving extra high results > total, just summing from individual tests for now
    /*$db_results = \Drupal::entityQueryAggregate('qwiz_result')
      ->aggregate('total_questions', 'sum')
      ->aggregate('attempted', 'sum')
      ->aggregate('seen', 'sum')
      ->aggregate('correct', 'sum')
      ->condition('course', $course_id)
      ->condition('subscription_id', $sub_id)
      ->condition('user_id', $uid)
      ->condition('class', $class_id)
      ->condition('qwiz_id', $class_ids_in_quiz, 'IN')
      ->execute();*/

    $params = [
      'course_id' => $course_id,
      'class' => $class_id,
    ];
    $total_questions = count($QwizardGeneral->getTotalQuizzes($params));

    $results[$total_label]['label'] = $total_label;
    $results[$total_label]['id'] = 'total';
    $results[$total_label]['total_questions'] = $total_questions;
    $results[$total_label]['attempted'] = 0;
    $results[$total_label]['seen'] = 0;
    $results[$total_label]['correct'] = 0;

    // Load topics for all quizzes, by loading test mode ID
    $multiquiz_quizzes = $statics['multiquiz_quizzes'];
    $qwiz_id = $multiquiz_quizzes[$course_id];
    $topic_names = $QwizardGeneral->getTopicsInQwiz($qwiz_id);

    foreach ($class_quizzes as $qwiz) {
      $qwiz_label = $qwiz->label();
      $qwiz_topics_field = $qwiz->get('topics');
      if (empty($qwiz_topics_field)) {
        // This quiz has no topics on it, just ignore it
        continue;
      }

      $topics_in_quiz = [];
      foreach ($qwiz_topics_field->getValue() as $topic_id) {
        $t = $topic_id['target_id'];
        $topics_in_quiz[$t] = $topic_names[$t];
      }

      if (empty($topics_in_quiz) || in_array($qwiz_label, $banlist_topic_names)) {
        continue;
      }

      // React API expects topic_id, not qwiz_id. This defaults to qwiz ID but prefers taxonomy ID
      $qwiz_topic_id = $qwiz->id();
      foreach ($topics_in_quiz as $topic_id => $topic_name) {
        if ($qwiz->label() == $topic_name) {
          $qwiz_topic_id = $topic_id;
          break;
        }
      }
      if ($qwiz_topic_id == $qwiz->id()) {
        //$message = "Qwiz with ID of ".$qwiz->id()." and label of ".$qwiz->label()." does not have a topic of the same name";
        //\Drupal::logger('qwizard')->notice($message);
      }

      $db_results = \Drupal::entityQueryAggregate('qwiz_result')
        ->aggregate('total_questions', 'sum')
        ->aggregate('attempted', 'sum')
        ->aggregate('seen', 'sum')
        ->aggregate('correct', 'sum')
        ->condition('qwiz_id', $qwiz->id())
        ->condition('class', $class_id)
        ->condition('course', $course_id)
        ->condition('subscription_id', $sub_id)
        ->condition('user_id', $uid)
        ->execute();

      $params = [
        'course_id' => $course_id,
        'class' => $class_id,
        'topics' => array_keys($topics_in_quiz),
      ];
      $total_questions_in_quiz = count($QwizardGeneral->getTotalQuizzes($params));
      // This can be used instead, just write an autofix test to confirm that it matches above first
      //$total_questions = $qwiz->getQuestionCount();

      $attempted = (int) ($db_results[0]['attempted_sum'] ?: 0);
      $seen = (int) ($db_results[0]['seen_sum'] ?: 0);
      $correct = (int) ($db_results[0]['correct_sum'] ?: 0);
      $results[$qwiz_label]['label'] = $qwiz->label();
      $results[$qwiz_label]['id'] = $qwiz_topic_id;
      $results[$qwiz_label]['total_questions'] = $total_questions_in_quiz;
      $results[$qwiz_label]['attempted'] = $attempted;
      $results[$qwiz_label]['seen'] = $seen;
      $results[$qwiz_label]['correct'] = $correct;

      // Add to totals
      $results[$total_label]['attempted'] += $attempted;
      $results[$total_label]['seen'] += $seen;
      $results[$total_label]['correct'] += $correct;
    }

    $new_results = [];
    foreach ($results as $topic_result) {
      $topic_result['name'] = $topic_result['label'];
      $new_results[$topic_result['id']] = $results_storage->create($topic_result);
    }

    // Score each group of topics using standardized scoring function
    $multiquiz_data = [];
    foreach ($new_results as $topic_id => $topic_all_results) {
      $topic_name = $topic_all_results->getName();

      $row = $this->scoreStudentResults([$topic_all_results], $topic_id, $topic_name);
      if (!empty($row[$topic_name])) {
        $multiquiz_data[$topic_name] = $row[$topic_name];
      }
    }

    return $multiquiz_data;
  }

  /**
   * Given an array of quiz result entities, will aggregate and score them
   *
   * @param $quiz_results
   * @param $forced_qwiz_id
   * @param $forced_label
   *
   * @return array
   * @todo should be used in qwreporting
   * @todo this works fine, but clean this up and allow for an array to be
   *       passed as well
   */
  public function scoreStudentResults($quiz_results, $forced_qwiz_id = NULL, $forced_label = NULL, $forced_total = NULL): array {
    $results = [];
    if (empty($quiz_results)) {
      return $results;
    }

    // Removed, but could be used to make all correct based on quiz type if desired. Works fine.
    $qwiz = $this->getQwiz();
    $qwiz_decrement_type = $qwiz->getQwPoolDecrSettings();

    /*// If we are decrementing on skipped, then just return 1 for existing
    if($qwiz_decrement_type['decr_skipped']){
      return 1;
    }

    // If we are decrementing on incorrect, and they chose an answer, then count it as correct
    if($qwiz_decrement_type['decr_wrong']){
      if(!empty($question_data['chosen_answer'])){
        return 1;
      }
    }*/

    foreach ($quiz_results as $quiz_result) {
      if (!empty($forced_qwiz_id)) {
        $qwiz_id = $forced_qwiz_id;
      }
      else {
        $qwiz_id = $quiz_result->getQuizId();
      }

      if (!empty($forced_label)) {
        $qwiz_label = $forced_label;
      }
      else {
        $qwiz_label = $quiz_result->getQuizJSONLabel();
      }
      $qwiz_id = (string) $qwiz_id;

      // Quiz required data not available, just skip it
      if (empty($qwiz_id) || empty($qwiz_label)) {
        continue;
      }

      $results[$qwiz_label]['label'] = $qwiz_label;
      $results[$qwiz_label]['id'] = $qwiz_id;
      $first_quiz_result = reset($quiz_results);
      $results[$qwiz_label]['qwiz_id'] = $first_quiz_result->getQuizId();

      // Uncomment these for debugging if needed
      //$results[$qwiz_label]['qwiz_ids'][$qwiz_id] = $qwiz_id;
      //$results[$qwiz_label]['result_ids'][] = $qwiz_id;

      if (empty($results[$qwiz_label]['total_questions'])) {
        $results[$qwiz_label]['total_questions'] = 0;
      }
      $results[$qwiz_label]['total_questions'] += $quiz_result->getTotalQuestions();
      if (empty($results[$qwiz_label]['seen'])) {
        $results[$qwiz_label]['seen'] = 0;
      }
      $results[$qwiz_label]['seen'] += $quiz_result->getSeen();
      if (empty($results[$qwiz_label]['attempted'])) {
        $results[$qwiz_label]['attempted'] = 0;
      }
      $results[$qwiz_label]['attempted'] += $quiz_result->getAttempted();
      if (empty($results[$qwiz_label]['correct'])) {
        $results[$qwiz_label]['correct'] = 0;
      }
      $results[$qwiz_label]['correct'] += $quiz_result->getCorrect();

      // Data validation, helps with rounding later. Results can come in > 100% without this currently
      if ($results[$qwiz_label]['correct'] > $results[$qwiz_label]['total_questions']) {
        $results[$qwiz_label]['correct'] = $results[$qwiz_label]['total_questions'];
      }
      if ($results[$qwiz_label]['attempted'] > $results[$qwiz_label]['seen']) {
        $results[$qwiz_label]['seen'] = $results[$qwiz_label]['attempted'];
      }
    }

    // Scoring.
    foreach ($results as $qwiz_label => $total_results) {
      $correct = $total_results['correct'];
      $total_questions = $total_results['total_questions'];
      $seen = $total_results['seen'];
      $attempted = $total_results['attempted'];
      $results[$qwiz_label]['actually_correct'] = $correct;

      $results[$qwiz_label]['score_all'] = $total_questions ? $correct / $total_questions : 0;
      $results[$qwiz_label]['score_seen'] = $seen ? $correct / $seen : 0;
      $results[$qwiz_label]['score_attempted'] = $attempted ? $correct / $attempted : 0;

      if ($qwiz_decrement_type['decr_wrong']) {
        //$correct = $attempted;
        $results[$qwiz_label]['correct'] = $attempted;
        $results[$qwiz_label]['actually_correct'] = $correct;
        $results[$qwiz_label]['score_all'] = $total_questions ? $attempted / $total_questions : 0;
      }
      if ($qwiz_decrement_type['decr_skipped']) {
        // @todo test, never had a decr_skipped class
        $results[$qwiz_label]['correct'] = $seen;
        $results[$qwiz_label]['actually_correct'] = $correct;
        $results[$qwiz_label]['score_all'] = $total_questions ? $seen / $total_questions : 0;
      }

      /*elseif($qwiz_decrement_type['decr_skipped']){
        // @todo untested
        #$results[$qwiz_label]['correct'] = $total_results['seen'];
        #$total_results['correct'] = $results[$qwiz_label]['correct'];
      }*/

      if (!empty($forced_total)) {
        $results[$qwiz_label]['total_questions'] = $forced_total;
      }

    }

    #dpm($results);
    return $results;
  }

  public function isSnapshotQuestionCorrect($question_data, $question_id, $all_question_nids, $pool_data) {
    $is_correct = 0;
    $possible_answers = [];
    $incorrect_in_pools = 0;

    /*# var_dump($question_data);
    var_dump(array_keys($pool_data)); exit;
    $decr_settings = $qwiz_result->getQuiz()->getQwPoolDecrSettings();*/

    if (empty($question_data['chosen_answer'])) {
      return $is_correct;
    }

    if ($question_data['chosen_answer'] == $question_data['correct_answer']) {
      $is_correct = 1;
    }

    if (!$is_correct) {
      if (!empty($pool_data['complete'][$question_id])) {
        $is_correct = 1;
      }
      elseif (!empty($pool_data['correct'][$question_id])) {
        $is_correct = 1;
      }
      elseif (!empty($pool_data['incorrect'][$question_id])) {
        // We know from pools the question is incorrect, mark it as such and prevent further testing
        $incorrect_in_pools = 1;
      }
    }

    if (!$is_correct && !$incorrect_in_pools) {
      // Question is not correct. See if the answer they chose is even available at present
      // Give them a free point if it is not available. This mostly affects imported users

      if (!empty($question_data['answers'])) {
        $possible_answers = array_keys($question_data['answers']);
      }
      if (empty($possible_answers) && !empty($question_data['answers_order'])) {
        $possible_answers = array_values($question_data['answers_order']);
      }
      // Either possible answers cannot be detected, or their chosen answer is not available anymore. +1
      if (!in_array($question_data['chosen_answer'], $possible_answers)) {
        $is_correct = 1;
      }
    }

    // Student answered a question that no longer exists in this class
    // Happens from import or a deleted/moved question, like for 100157
    // Just give them a free point
    if (!$is_correct && !$incorrect_in_pools && empty($possible_answers) && in_array($question_id, $all_question_nids)) {
      $is_correct = 1;
    }

    return $is_correct;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Student Results entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Student Results entity.'))
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the Student Results is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['subscription_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subscription ID'))
      ->setDescription(t('The students subscription id of the Quiz Results Entity entity.'))
      ->setCardinality(1)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'courses' => 'courses',
          ],
        ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '10',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setDescription(t('The course of the Question Pool entity.'))
      ->setCardinality(1)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'courses' => 'courses',
          ],
        ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '10',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['class'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('The class this test covers.'))
      ->setCardinality(1)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'classes' => 'classes',
          ],
        ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '25',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['results'] = BaseFieldDefinition::create('jsonb')
      ->setLabel(t('Calculated Student Results'))
      ->setDescription(t('JSON Array of student results.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);
    return $fields;
  }

  /**
   * Use this to debug questions NOT answered correctly in a topic ID. Will
   * var_dump & exit.
   *
   * @param $test_topic
   * @param $course_id
   * @param $class_id
   * @param $topic_results
   *
   * @return void
   */
  private function debugFindQuestionsNotInTopicID($test_topic, $course_id, $class_id, $topic_results) {
    /*$all_nodes_in_topic = \Drupal::entityQuery('node')
      ->condition('type', 'qw_simple_choice')
      ->condition('field_topics', [$test_topic], 'IN')
      ->condition('field_courses', $course_id)
      ->condition('field_classes', $class_id)
      ->execute();*/

    $params = [
      'cache' => 0,
      'course_id' => $course_id,
      'class' => $class_id,
      'topics' => [$test_topic],
    ];
    //$params['topics'] = array_keys($topic_results);
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $all_nodes_in_topic = $QwizardGeneral->getTotalQuizzes($params);

    $data = [];
    foreach ($all_nodes_in_topic as $key => $value) {
      $data[$value] = $value;
    }
    if (!empty($topic_results[$test_topic]['correct_questions'])) {
      foreach ($topic_results[$test_topic]['correct_questions'] as $qid => $value) {
        if (!empty($data[$qid])) {
          unset($data[$qid]);
        }
      }
    }
    // Questions Not in Quiz
    var_dump('There are ' . count($all_nodes_in_topic) . ' nodes in topic ' . $test_topic);
    if (!empty($topic_results[$test_topic]['correct_questions'])) {
      var_dump('There are ' . count($topic_results[$test_topic]['correct_questions']) . ' nodes marked as correct for user');
    }
    var_dump('Nodes NOT answered correctly below:');
    var_dump($data);
    var_dump('All data for this topic below');
    var_dump($topic_results[$test_topic]);
    exit;
  }

  private function debugFindQuestionsCorrectDifferentFromPools($course_id, $class_id, $correct_questions) {
    if ($class_id != 461) {
      //return;
    }

    $pool = QwPool::getPoolForClass($class_id, $this->getOwnerId(), $this->getSubscriptionId());
    $completed_pool_question_json = $pool->getQuestionsCompleted();

    if (count($completed_pool_question_json) != count($correct_questions)) {
      $m = 'Results say there are ' . count($correct_questions) . ' correct questions, but pools say ' . count($completed_pool_question_json);
      dpm($m);

      // Find the difference
      foreach ($completed_pool_question_json as $correctly_answered_question_from_pool) {
        if (empty($correct_questions[$correctly_answered_question_from_pool])) {
          var_dump("Question ID " . $correctly_answered_question_from_pool . " was marked as correct in the pool, but is not correct in total questions array");
        }
      }
      exit;
    }
    else {
      var_dump('they are the same at ' . $completed_pool_question_json . ' questions complete in pool and in results');
    }

  }

  private function debugFindQuestionsInTotalListNotAnsweredCorrectly($correct_questions, $all_questions_in_class) {
    // Format them so they're the same
    $formatted_correct = [];
    foreach ($correct_questions as $nid => $amount_correct) {
      $formatted_correct[$nid] = $nid;
    }

    $formatted_all = [];
    foreach ($all_questions_in_class as $i => $nid) {
      $formatted_all[$nid] = $nid;
    }
    var_dump('There are ' . count($formatted_correct) . ' questions marked as correct.');
    var_dump('There are ' . count($formatted_all) . ' questions in the  class.');

    var_dump(array_merge(array_diff($formatted_correct, $formatted_all), array_diff($formatted_all, $formatted_correct)));

    exit;

    // Find the difference
  }

}
