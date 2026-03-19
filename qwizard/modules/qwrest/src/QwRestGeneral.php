<?php

namespace Drupal\qwrest;


use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\user\Entity\User;

/**
 * Class QwRestGeneral.
 */
class QwRestGeneral {
  protected $user_has_answer_role = null;

  /**
   * Constructs a new QwRestGeneral object.
   */
  public function __construct() {
    //@todo DI
  }

  /**
   * Get input parameters, either from $_GET or from $payload
   * Higher ones are prioritized for duplicates
   * $GET_name=>$php_var_name for array structure. Such as:
   * ['course' => 'course_id'] for $return['course_id'] = $_GET['course_id']
   * $payload can be an array to check in addition to $_GET
   * $options to be used in the future
   *
   * @param array $get_params_to_get
   * @param array $options
   * @param array|null $payload
   * @return array
   */
  public function getInputsParams(array $get_params_to_get, ?array $options = [], ?array $payload = []): array
  {

    $input_params = [];
    $request = \Drupal::request();

    if(empty($payload)) {
      // @todo use a more drupaly method to get request input
      $payload = file_get_contents('php://input');
      if (!empty($payload)) {
        $payload = json_decode($payload, true);
      }
    }

    $payload_exists = !empty($payload);
    foreach(array_reverse($get_params_to_get) as $GET_name=>$php_var_name){
      // Handles keyless array
      if (is_int($GET_name)) {
        $GET_name = $php_var_name;
      }

      if ($payload_exists && isset($payload[$GET_name])) {
        $input_params[$php_var_name] = $payload[$GET_name];
      } elseif (!empty($request->request->get($GET_name))) {
        $input_params[$php_var_name] = $request->request->get($GET_name);
      } elseif (!empty($request->query->get($GET_name))) {
        $input_params[$php_var_name] = \Drupal::request()->query->get($GET_name);
      }
    }

    return $input_params;
  }

  /**
   * /api-v1/site-results
   * Gets results for all active students
   * Is cached
   * @param int $course_id
   * @param bool $force_fresh_data
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @todo this is very slow when cache is cleared
   *
   */
  public static function getSiteResultsData($course_id = null, bool $force_fresh_data = false): array
  {
    $payload['results_list'] = [];
    $statics = \Drupal::service('qwizard.site_results');
    $courses = \Drupal::service('qwizard.coursehandler')->getActiveCourses();
    $QWGeneral = \Drupal::service('qwizard.general');

    if(!empty($courses[$course_id])){
      $courses = [$course_id => $courses[$course_id]];
    }

    $today = $QWGeneral->getDateStorageString('now');
    $month = $QWGeneral->getDateStorageString('1 month ago');
    $week = $QWGeneral->getDateStorageString('1 week ago');
    $day = $QWGeneral->getDateStorageString('1 day ago');

    foreach($courses as $course_id => $course_name) {
      if($course_id > 0 ){
        $payload['results_list'][$course_name]['month'] = $statics->getResultsForCourses($course_id, 'month',$force_fresh_data, $today, $month);
        $payload['results_list'][$course_name]['week'] = $statics->getResultsForCourses($course_id, 'week', $force_fresh_data, $today, $week);
        $payload['results_list'][$course_name]['day'] = $statics->getResultsForCourses($course_id, 'day', $force_fresh_data, $today, $day);
      }
    }

    return $payload;
  }

  public function getMultipleSessionArrays($qwiz_results, $user_id, $subscription_id, $params = []){

    $query = \Drupal::entityQuery('qwpool')
      ->condition('user_id', $user_id)
      ->condition('subscription_id', $subscription_id)
      ->sort('status', 'DESC');
    $pids  = $query->execute();
    $pool_storage = \Drupal::entityTypeManager()->getStorage('qwpool');
    $pools         = $pool_storage->loadMultiple($pids);
    $pools_by_class = [];
    foreach($pools as $pool){
      $pools_by_class[$pool->getClassId()] = $pool;
    }
    $return = [];
    foreach($qwiz_results as $qwiz_result){
      $class_id = $qwiz_result->getClass();
      if (isset($pools_by_class[$class_id])) {
        $params['loaded_pool'] = $pools_by_class[$class_id];
      }
      else {
        // If the pool is missing from the bulk load, pass null.
        // getSessionArray will attempt to load it individually or handle the null.
        $params['loaded_pool'] = NULL;
      }
      $return[$qwiz_result->id()] = $this->getSessionArray($qwiz_result, $params);
    }

    return $return;
  }

  /**
   * /api-v1/qwiz-session
   *
   * @param $result
   * @param array $params
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityStorageException
   */
  public function getSessionArray($result, array $params = []): array
  {
    $default_params = [
      'include_snapshot' => TRUE,
      'update_reviewed_time' => FALSE,
      'published_pools_only' => true,
      'minimal' => false,
      'loaded_pool' => null,
      'lang' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
    ];
    $params = array_merge($default_params, $params);
    $include_snapshot = $params['include_snapshot'];
    $update_reviewed_time = $params['update_reviewed_time'];
    $minimal = $params['minimal'];


    if ($result instanceof QwizResult) {
      $qwiz_result = $result;
    }
    else {
      // Result was passed as an ID
      $qwiz_result_storage = \Drupal::entityTypeManager()
        ->getStorage('qwiz_result');
      $qwiz_result = $qwiz_result_storage->load($result);
    }
    if (empty($qwiz_result)) {
      return [];
    }

    // This is a review.
    //if (!empty($qwiz_result->get('end')->value)) {
    if ($update_reviewed_time) {
      $qwiz_result->setReviewedTime(time());
      $qwiz_result->save();
    }


    // Get test time.
    $test_time = $qwiz_result->get('total_questions')->value;
    if(!$minimal) {
      //@todo too many queries, make faster
      $qwiz = \Drupal::entityTypeManager()
        ->getStorage('qwiz')
        ->load($qwiz_result->get('qwiz_id')->value);
      $time_per_question = $qwiz->get('time_per_question')->value;

      $active_profile = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => \Drupal::currentUser()->id(),
          'type' => 'qwizard_profile',
        ]);

      $active_profile = reset($active_profile);
      if (!empty($time_per_question) && !empty($active_profile) && isset($active_profile->field_special_accommodations) && $active_profile->field_special_accommodations->value) {
        // Checkbox is checked. Get the selected time option. If nothing is
        // selected, we are using 2 minutes 10 secs (130s).
        $time_option = 130;
        // Make sure the field exists to avoid any errors.
        if ($active_profile->hasField('field_time_options')) {
          $time_option_selected = $active_profile->get('field_time_options')->getString();
          if (!empty($time_option_selected)) {
            $time_option = $time_option_selected;
          }
        }
        $test_time = $test_time * $time_option;
      }
      else {
        $test_time = $time_per_question * $test_time;
      }
    }


    if(!empty($params['loaded_pool'])){
      $pool = $params['loaded_pool'];
    }else {
      $pool = $qwiz_result->getResultPool($params['published_pools_only']);
    }
    $qwiz = $qwiz_result->getQuiz();
    $questionsInQwizPool = 0;
    $questionsComplete = 0;
    if(!$params['minimal']) {
      if ($pool) {
        $questionsInQwizPool = (int)$pool->getQuestionCountByQwiz($qwiz);
        $questionsComplete = (int)$pool->getCompleteCountByQwiz($qwiz);
      }
    }

    $nowDate = new \Datetime($qwiz_result->get('start')->value);
    $endDate = new \Datetime($qwiz_result->get('end')->value);
    $fixed_interval = $nowDate->diff($endDate);


    // Combine result and snapshot to session array.
    $session_array = [
      'resultId'        => $qwiz_result->id(),
      'uuid'            => $qwiz_result->get('uuid')->value,
      'name'            => $qwiz_result->get('name')->value,
      'user_id'         => $qwiz_result->get('user_id')->value,
      'qwiz_id'         => $qwiz_result->get('qwiz_id')->value,
      'qwiz_rev'        => $qwiz_result->get('qwiz_rev')->value,
      'subscription_id' => $qwiz_result->getSubscriptionId(),
      'created'         => $qwiz_result->get('created')->value,
      'changed'         => (string) $qwiz_result->get('changed')->value,// (string) added here due to inconsistent typing
      'start'           => $qwiz_result->get('start')->value,
      'end'             => $qwiz_result->get('end')->value,
      'elapsed_sec'     => $fixed_interval->format('%s'), // if qwiz test page didn't open. please commet this line
      'elapsed_min'     => $fixed_interval->format('%i'), // if qwiz test page didn't open. please commet this line
      'elapsed_hour'    => $fixed_interval->format('%h'), // if qwiz test page didn't open. please commet this line
      'reviewed'        => $qwiz_result->get('reviewed')->value,
      'qwiz_time'       => $test_time,
      'attempted'       => $qwiz_result->get('attempted')->value,
      'score_attempted' => $qwiz_result->get('score_attempted')->value,
      'score_seen'      => $qwiz_result->get('score_seen')->value,
      'score_all'       => $qwiz_result->get('score_all')->value,
      'total_questions' => $qwiz_result->get('total_questions')->value,
      'seen'            => $qwiz_result->get('seen')->value,
      'correct'         => $qwiz_result->get('correct')->value,
      'snapshot_id'     => $qwiz_result->get('snapshot')->target_id,
      'answer_key'      => 0,
      'pool_complete'   => ($questionsInQwizPool > 0 && ($questionsInQwizPool - $questionsComplete) === 0)
    ];

    if ($include_snapshot) {
      $session_array['snapshot'] = $qwiz_result->getSnapshotArray();
    }

    $user_has_answer_key_role = $this->userHasAnswerKeyRole();
    if($user_has_answer_key_role){
      $session_array['answer_key'] = 1;
    }


    // Post-processing on question array
    // @todo use a $param instead of $_GET here, this is on many pages
    // @todo get flashcards directly instead of unsetting
    $type = \Drupal::request()->query->get('type');
    if ($type == 'qw_flashcard') {
      foreach ($session_array['snapshot']['questions'] as $key => $question) {
        if (!empty($question['question_type']) && $question['question_type'] != $_GET['type']) {
          unset($session_array['snapshot']['questions'][$key]);
          if (!empty($session_array['snapshot']['question_summary'][$key])) {
            unset($session_array['snapshot']['question_summary'][$key]);
          }
        }
      }
    }

    // Workaround for questions array being saved as object.
    // @todo: can remove this if all snapshots are fixed.
    // @todo write behat test to loop see if this issue still exists
    if (!empty($session_array['snapshot']['questions'])) {
      $session_array['snapshot']['questions'] = array_values($session_array['snapshot']['questions']);
    }

    return $session_array;
  }

  /**
   * /api-v1/qwiz-session
   *
   * Sets the session array.
   *
   * @param $session_array
   * @param QwizResult $qwiz_result
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws EntityMalformedException
   */
  public static function setSessionArray($session_array, QwizResult $qwiz_result, $recalculate_results = false)
  {
    if (!empty($session_array['end'])) {
      $recalculate_results = true;
    }

    if (!isset($session_array['snapshot']['last_question_viewed'])) {
      // The user can hit this if they have no questions available to view due to finishing a course. No action needed.
    } else {
      $current_question_idx = $session_array['snapshot']['last_question_viewed'];
      if (!isset($current_question_idx)) {
        \Drupal::logger('QwizSessionResource')
          ->error('The session array snapshot(QR - @qr) structure is missing last_question_viewed.', ['@qr' => $qwiz_result->id()]);
        throw new EntityMalformedException('Snapshot structure incorrect.');
      }
      $current_question_id = $session_array['snapshot']['last_question_viewed'];
      $chosen_ans_id = $session_array['snapshot']['questions'][$current_question_idx]['chosen_answer'];
      $qwiz_result->scoreQuestion($chosen_ans_id, $current_question_id, $current_question_idx, $recalculate_results);

    }

    // Combine result and snapshot to session array.
    if ($recalculate_results) {
      $qwiz_result->endQwizResult(false);
    }
  }

  /**
   * Determines if current user has the role "answer_key" or not
   * Uses $this->user_has_answer_role, will populate the variable if it is not set
   * Used to limit queries
   *
   * @return int
   */
  private function userHasAnswerKeyRole(): int {
    if($this->user_has_answer_role === null){
      $current_user = User::load(\Drupal::currentUser()->id());
      if ($current_user->hasRole('answer_key')) {
        $this->user_has_answer_role = 1;
      }else{
        $this->user_has_answer_role = 0;
      }
    }

    return $this->user_has_answer_role;
  }
}
