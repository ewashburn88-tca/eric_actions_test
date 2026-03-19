<?php

namespace Drupal\qwizard;

use Dompdf\Exception;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Session\AccountInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class QwizardGeneral.
 */
class QwizardGeneral implements QwizardGeneralInterface {

  /**
   * Constructs a new QwizardGeneral object.
   */
  public function __construct() {

  }

  /**
   * Returns an array of content types that are questions for quiz wizard.
   *
   * @return array ['type_machine_name' => 'label']
   */
  public static function getListOfQuestionTypes($just_keys = FALSE) {

    // Get question types from config.
    $options = [];
    $config  = \Drupal::config('qwizard.qwizardsettings');
    $qtypes  = $config->get('question_types');
    if ($just_keys) {
      return $qtypes;
    }
    $node_types = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('node');
    if (!empty($qtypes)) {
      foreach ($qtypes as $qtype) {
        if (isset($node_types[$qtype])) {
          $options[$qtype] = $node_types[$qtype]['label'];
        }
      }
    }
    return $options;
  }

  /**
   * Retrieves a list of question ids as from entity query.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param array                        $question_types
   *
   * @return array|int
   */
  public static function getAllQuestionIdsForCourse(Term $course, $question_types = []) {
    $cache_key = 'getAllQuestionIdsForCourse_'.$course->id().'_'.json_encode($question_types);
    $QwCache = \Drupal::service('qwizard.cache');
    $cache = $QwCache->checkCache($cache_key);
    if(!empty($cache)) {
      return $cache;
    }else {
      if (empty($question_types)) {
        $question_types = self::getListOfQuestionTypes();
        $question_types = array_keys($question_types);
        if (empty($question_types)) {
          // @todo: Throw error message and redirect to admin/qwizard/config/qwizardsettings
        }
      }
      $query = \Drupal::entityQuery('node')
        ->condition('type', $question_types, 'IN')
        ->condition('field_courses', $course->id())
        ->condition('status', TRUE);
      $nids = $query->execute();
      $return = empty($nids) ? [] : $nids;
      $QwCache->setCacheFile($cache_key, $return, true);

      return $return;
    }
  }

  /**
   * Retrieves a list of question ids as from entity query.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param array                        $question_types
   *
   * @return array|int

  public static function getAllQuestionIdsForClass(Term $class, $question_types = []) {
    if (empty($question_types)) {
      $question_types = self::getListOfQuestionTypes();
      $question_types = array_keys($question_types);
      if (empty($question_types)) {
        // @todo: Throw error message and redirect to admin/qwizard/config/qwizardsettings
      }
    }
    $query = \Drupal::entityQuery('node')
      ->condition('type', $question_types, 'IN')
      ->condition('field_classes', $class->id())
      ->condition('status', TRUE);
    $nids  = $query->execute();
    return empty($nids) ? [] : array_combine($nids, $nids);
  }*/

  /**
   * Formats string date or timestamp to ISO datetime.
   *
   * @param        $datetime
   * @param string $tz
   *
   * @return string
   * @throws \Exception
   */
  public static function formatIsoDate($datetime, $tz = 'UTC') {
    if ($tz != 'UTC' && !in_array($tz, timezone_identifiers_list())) {
      throw new Exception('$tz must be valid PHP timezone string.');
    }
    $timezone = new \DateTimeZone($tz);
    if (!($datetime instanceof \DateTime)) {
      if ($datetime == intval($datetime)) {
        // Assume timestamp.
        $date = new \DateTime('NOW', $timezone);
        $date->setTimestamp($datetime);
      }
      else {
        try {
          $date = new \DateTime($datetime, $timezone);
        }
        catch (Exception $e) {
          //display custom message
          echo $e->getMessage();
          return FALSE;
        }

      }
      return $date->format('c');
    }
    return $datetime->format('c');
  }

  /**
   * Formats string date or timestamp to ISO datetime.
   *
   * @param        $datetime
   * @param string $tz
   *
   * @return string
   * @throws \Exception
   */
  public static function getDateTime($datetime, $tz = 'UTC') {
    if ($tz != 'UTC' && !in_array($tz, timezone_identifiers_list())) {
      throw new Exception('$tz must be valid PHP timezone string.');
    }
    $timezone = new \DateTimeZone($tz);

    if (!($datetime instanceof \DateTime)) {
      if (is_int($datetime)) {
        // Assume timestamp.
        $date = new \DateTime('NOW', $timezone);
        $date->setTimestamp($datetime);
      }
      else {
        try {
          $date = new \DateTime($datetime, $timezone);
        }
        catch (Exception $e) {
          //display custom message
          \Drupal::logger('qwizard')->error($e->getMessage().' | '.$e->getTraceAsString());
          throw new \Exception("Was not possible to get Date Time");
        }
      }
      return $date;
    }
    return $datetime;
  }

  /**
   * Takes an unknown value for a user account and returns an AccountInterface.
   *
   * @param null|int|\Drupal\user\User $var
   *
   * @return FALSE|AccountInterface|UserInterface
   *   At least an AccountInterface.
   */
  public static function getAccountInterface($var = 'current') {
    $account = false;
    if ($var instanceof AccountInterface || $var instanceof UserInterface) {
      return $var;
    }
    elseif ($var == NULL || $var == 'current') {
      $account = User::load(\Drupal::currentUser()->id());
    }
    elseif (is_numeric($var)) {
      $account = User::load($var);
    }

    if(!$account instanceof AccountInterface){
      Throw new \Exception('QwizardGeneral::getAccountInterface() was unable to load user from '.json_encode($var));
    }

    return $account;
  }

  /**
   * Impements transformRootRelativeUrlsToAbsolute() using base url from site.
   *
   * @param $html
   *
   * @return string
   */
  public static function transformRootRelativeUrlsToAbsolute($html) {
    //Check for strings in need of transforming first, the HTML function is quite slow as it loads DOM
    if(strpos($html, '"/') !== false){
      $base_url = \Drupal::request()->getSchemeAndHttpHost();
      $html = Html::transformRootRelativeUrlsToAbsolute($html, $base_url);
    }
    return $html;
  }

  /**
   * Converts a nested array or object to ul.
   *
   * @param $array
   *
   * @return string
   */
  public static function arrayToUl($array) {
    if (is_object($array)) {
      $array = (array)$array;
    }
    $output = "<ul>";
    foreach ($array as $key => $value) {
      if (is_int($value) || is_string($value)) {
        $output .=  "<li>" . $value;
      }
      elseif (is_array($value) || is_object($value)) {
        $output .= self::arrayToUl($value);
      }
      $output .=  "</li>";
    }
    $output .=  "</ul>";

    return $output;
  }

  /**
   * Given a product variation term value,
   * Loads the 'commerce_product_attribute_value' data to get months, uses the text key in key|text on the select list
   * returns course duration as an int
   *
   * @param int $pv_id
   * @param bool $increase_offset
   * @return int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getCourseDurationForProductVariationTermID(int $pv_id, bool $increase_offset = true, $start_time = 'now', $end_time = NULL): int{
    $now = QwizardGeneral::getDateTime($start_time)->getTimestamp();
    $term_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value');
    $term_options = $term_storage->loadMultipleByAttribute('term');
    $duration_text = $term_options[$pv_id]->getName();
    if (empty($end_time)) {
      $end_time = $duration_text;
    }
    $end_timestamp = QwizardGeneral::getDateTime($end_time)->getTimestamp();
    // Raw course duration from /admin/commerce/product-attributes/manage/term
    $course_duration = round(($end_timestamp - $now) / (60 * 60 * 24));

    // ZUKU-1216 - don't increase duration on 320 products
    if(str_contains($duration_text, '320 ')){
      $increase_offset = false;
    }

    if($increase_offset) {
      $course_duration = QwizardGeneral::increaseCourseDurationOffset($course_duration);
    }

    return $course_duration;
  }

  /**
   * @param int $course_duration
   * @return int
   */
  public static function increaseCourseDurationOffset(int $course_duration): int
  {
    // Adjust course duration as per custom logic as needed
    if($course_duration < 47){
      // 45 days or less
      $course_duration = $course_duration +2;
    } elseif($course_duration <= 62) {
      // 2 months or less
      $course_duration = $course_duration +4;
    } elseif($course_duration <= 95) {
      // 3 months or less
      $course_duration = $course_duration +5;
    } elseif($course_duration <= 125) {
      // 4 months or less
      $course_duration = $course_duration +6;
    } elseif($course_duration <= 155) {
      // 5 months or less
      $course_duration = $course_duration +7;
    } else {
      // 6 months or less
      $course_duration = $course_duration +10;
    }

    return $course_duration;
  }

  /**
   * Used to get a DrupalDateTime object with date format
   * Timezone defaults to DateTimeItemInterface::DATETIME_STORAGE_FORMAT
   * follows docs https://www.drupal.org/docs/8/core/modules/datetime/datetime-overview
   *
   * @param string $string
   * @param null $timezone
   * @return string
   */
  public static function getDateStorageString(string $string, $timezone = null): string {
    if(empty($timezone)){
      $timezone = DateTimeItemInterface::STORAGE_TIMEZONE;
    }

    $date = new DrupalDateTime($string);
    $date->setTimezone(new \DateTimezone($timezone));
    $date = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    return $date;
  }

  /**
   * Given two dates, returns the difference between them in days. Allows for either strtotime() strings or raw timestamps or DateTime objects
   * @param $start
   * @param $finish
   * @return int
   * @throws \Exception
   */
  public static function estimateLengthFromDates($start, $finish): int{
    // Strtotime strings
    if(is_string($start)){
      $start = strtotime($start);
    }
    if(is_string($finish)){
      $finish = strtotime($finish);
    }

    // DateTime objects
    if(is_object($start)){
      $start = $start->getTimestamp();
    }
    if(is_object($finish)){
      $finish = $finish->getTimestamp();
    }
    // Else just handle as raw int timestamp
    $final = round(($finish - $start) / 60 / 60 / 24);

    if($final < 0){
      $final = 0;
      \Drupal::logger('estimateLengthFromDates')->error('Start and finish dates are incorrect, start date needs to be before finish date. Start='.$start.' End='.$finish);
    }

    return $final;
  }

  /**
   * Displays a warning on the frontend if a user is already subscribed to a course+
   *
   * @param AccountInterface $user
   * @param int $course_id
   * @return void
   */
  public static function AlreadySubscribedWarning(AccountInterface $user, int $course_id)
  {
    // @todo use DI for $membershipHandler call here
    $membershipHandler = \Drupal::service('qwizard.membership');
    $membershipHandler->setAcct($user);
    $message = '';

    $all_memberships = $membershipHandler->isUserSubscribedToCourse($course_id, false, 0, true);
    if ($all_memberships >= QwizardGeneral::getMaxSubscriptionAmount()) {
      $message = 'Course subscription renewals are limited to '.QwizardGeneral::getMaxSubscriptionAmount().' times. You will need to contact support in order to extend your subscription.';
      \Drupal::messenger()->addError($message);
    }
    // If user has at least one subscription to course $sub_id, then display warning
    /*elseif ($all_memberships) {
      // Adjust the message depending on if it's expired or not
      $memberships_removing_expired = $membershipHandler->getUserMemberships(true, $course_id);
      if (empty($memberships_removing_expired)) {
        // User had a previous sub that expired
        //$message = 'You had a subscription to this course that expired, are you sure you want to purchase another subscription? If you do, all your current results will be reset.';
      } else {
        // User has an active sub, will have duration extended
        //$message = 'You currently have time left on this subscription, are you sure you want to purchase another? If you do, all your current results will be reset and the remaining time from your current membership will be added to the new membership.';
      }

      //\Drupal::messenger()->addWarning($message);
    }
    // On $all_memberships == 0 this function does nothing
    */
  }

  /**
   * Returns max allowed subscriptions, either as the site default or for a passed subscription
   *
   * @param null $subscription
   * @return int
   */
  public static function getMaxSubscriptionAmount($subscription = null): int
  {
    $extension_limit = \Drupal::service('qwizard.general')->getStatics('extension_default_limit');
    if (!empty($subscription)) {
      $sub_extension_limit = $subscription->getExtensionLimit();
      if(isset($sub_extension_limit)){
        $extension_limit = $sub_extension_limit;
      }
    }

    return $extension_limit;
  }

  /**
   * Returns an array of users and a display title
   * Designed to be used in #options elements
   * $params = ['role' => ['association_admin', 'administrator'], 'field' => 'display_name', 'active' => 1, 'by_course' => null]
   * @todo check performance since it's loading a large data set. Could be cached as well if necessary
   *
   * @param $params
   * @return array
   */
  public static function getAllUsers($params): array {
    $user_list = [];
    if(empty($params['field'])) $params['field'] = '';
    if(empty($params['role'])) $params['role'] = [];
    if(empty($params['active'])) $params['active'] = 1;

    $query = \Drupal::entityQuery('user');
    if($params['active'] == 1){
      $query = $query->condition('status', 1);
    }
    if(!empty($params['role'])){
      $query = $query->condition('roles', $params['role'], 'IN');
    }
    $uids  = $query->execute();

    // This function may require batching if > 2000 users due to memory limitations on loading all users.
    // using dpm() as a dev-only setMessage
    // https://newbedev.com/get-a-list-of-all-users-and-into-an-array
    if(count($uids) > 2000) dpm('QwizardGeneral::getAllUsers() call returned a large data set of '.count($uids).' users. This page may eventually crash.');

    $user_list = User::loadMultiple($uids);

    // Formatting the data if desired, otherwise returns all User objects
    if(!empty($params['field'])){
      $displayed_user_list = [];
      foreach($user_list as $user){
        $displayed_user_list[$user->id()] = $user->getDisplayName();
      }
      $user_list = $displayed_user_list;
    }

    // @todo caching/performance. could invalidate cache with triggers

    return $user_list;
  }

  /**
   * Gets user's marked questions.
   *
   * @todo: Should create a marked questions service.
   *
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getMarkedQuestions($params = []) {
    $defaults = [
      'status' => 1,
      'type' => 'all',
      'user' => \Drupal::currentUser()->id(),
      'course' => null,
      'loaded' => false,
      'question_id' => null,
      'as_question_ids' => true,// Will give marked question entity ID's if false
    ];
    $options = array_merge($defaults, $params);
    if(empty($options['course'])){
    $current_course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
    if (!empty($current_course)) {
      $options['course'] = $current_course->id();
    }
    else {
      // We should have a curren course, if not, something is wrong.
      \Drupal::logger('qwgeneral')->error('No current course in getMarkedQuestions()');
      return [];
    }
    }
    if(!empty($options['as_question_ids'])){
      $options['loaded'] = 1;
    }

    $database = \Drupal::database()->select('marked_question_field_data', 'mq')
      ->fields('mq',['id']);
    $database->join('node', 'n','n.nid=mq.question');
    if($options['status'] != 'all') {
      $database->condition('mq.status', $options['status']);
    }
    $database->condition('mq.uid', $options['user']);
    if($options['course'] != 'all') {
      $database->condition('mq.course', $options['course']);
    }
    if($options['type'] != 'all') {
      $database->condition('n.type', $options['type']);
    }
    if(!empty($options['question_id'])){
      $database->condition('mq.question', $options['question_id']);
    }

    $markedQsRaw = $database->execute()->fetchAll();

    $markedQs = [];
    foreach($markedQsRaw as $item){
      $markedQs[] = $item->id;
    }

    if($options['loaded']){
      $markedQs = \Drupal::entityTypeManager()->getStorage('marked_question')->loadMultiple($markedQs);
    }

    if(!empty($options['as_question_ids'])) {
      $marked_questions = [];
      foreach ($markedQs as $marked_question) {
        $question_id = $marked_question->getQuestionID();
        $marked_questions[$question_id] = $question_id;
      }
      $markedQs = $marked_questions;
    }

    return $markedQs;
  }

  /**
   * Throws a warning if the user does not have a student profile set up yet
   * Warnings can be customized further by profile type or the issue with them
   *
   * @param $user
   * @param $profile_type
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function EmptyProfileWarning($user, $profile_type){
    $data = ['status' => 1, 'name' => $profile_type, 'message' => ''];
    $profile_route = \Drupal\Core\Url::fromUri('internal:/user/' . $user->id().'/'.$profile_type);

    $link_text = 'Please fill out your '.str_replace('_profile', '', $profile_type).' profile';
    if($profile_type == 'qwizard_profile'){
      $link_text = 'Customize your testing experience';
    }
    $message = \Drupal\Core\Link::fromTextAndUrl($link_text, $profile_route)->toString();

    // Only test if the user has access to create the specified
    if($profile_route->access()) {
      $profile = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'uid' => $user->id(),
          'type' => $profile_type,
        ]);
      $show_warning = false;

      if(empty($profile)){
        $show_warning = true;
      }
      // Student profile exists, check that field_education is actually set
      elseif($profile_type == 'student'){
        $profile = reset($profile);
        if(empty($profile->get('field_education')->getValue())) $show_warning = true;
      }
      elseif($profile_type == 'qwizard_profile'){
        $profile = reset($profile);
        if(empty($profile->get('field_completion_date')->getValue())) $show_warning = true;
      }

      if ($show_warning) {
        $data['status'] = 0;
        $data['message'] = $message;
        #\Drupal::messenger()->addWarning(\Drupal\Core\Render\Markup::create($message));
      }
    }

    return $data;
  }

  /**
   * Can be used to determine what env is being run.
   *
   * If called using 'string', will just return development/staging/uat/production
   * If called using 'number' (or nothing), will return 0=production, 1=staging, 2=uat, 3=development
   * For example, if(QwizardGeneral::getCurrentEnv('number') >= 1){ // Do non-prod things }
   * For example, if(QwizardGeneral::getCurrentEnv('string') == 'development'){ // Do dev things }
   * Will throw an exception if unable to determine environment
   *
   * @param string $type ('number' or 'string')
   * @return int|string
   * @throws Exception
   */
  public static function getCurrentEnv(string $type = 'number') {
    $env = FALSE;

    // Keep these in descending order, prod down to dev.
    $possible_envs = ['production', 'uat', 'staging', 'development'];
    foreach ($possible_envs as $possible_env) {
      $data[$possible_env] = \Drupal::config('config_split.config_split.' . $possible_env)
        ->get('status');
    }

    // Figure out which is currently used, preferring the highest available.
    $i = -1;
    foreach ($data as $key => $value) {
      $i++;
      if ($value) {
        if ($type == 'string') {
          $env = $key;
        }
        elseif ($type == 'number') {
          $env = $i;
        }
        break;
      }
    }

    // Crash in an obvious way by throwing an exception.
    if ($env === FALSE) {
      throw new Exception('getCurrentEnv could not detect environment.');
    }

    return $env;
  }

  /**
  getMarkedQuestionsForSpecificUserForCustomReportFromDevel(110076, 201, [263]);
   */
  function getMarkedQuestionsForSpecificUserForCustomReportFromDevel($user_id, $course_id, $topics_we_want){


    #$course_to_get = 201;#BCSE is 201
    #$user_to_get = 110076;

    $qw_general = \Drupal::service('qwizard.general');
    $existing_marked_questions = $qw_general->getMarkedQuestions([
      'status' => 1,
      'loaded' => true,
      'user' => $user_id,
      'course' => $course_id,
      'as_question_ids' => false
    ]);
#dpm($existing_marked_questions);

    #$topics_we_want = [263/*preventitive med*/];
    $params = ['types' => ['qw_simple_choice'], 'status' => 1, 'cache' => 0, 'ignore_access_check' => 1, 'topics' => $topics_we_want];
    $nids_in_topic_we_want = array_values($qw_general->getTotalQuizzes($params));

#dpm($nids_in_topic_we_want);

    $marked_questions_in_topic_we_want = [];
    $storage = \Drupal::service('entity_type.manager')->getStorage('node');
    foreach ($existing_marked_questions as $marked) {
      $marked_nid = $marked->get('question')->getValue()[0]['target_id'];
      #dpm($marked_nid);

      if (in_array($marked_nid, $nids_in_topic_we_want)) {
        $marked_questions_in_topic_we_want[$marked_nid] = $storage->load($marked_nid);
      }
    }

    dpm($marked_questions_in_topic_we_want);
# Now report on them

    $report = [];
    foreach ($marked_questions_in_topic_we_want as $nid => $question) {
      $question_text = $question->get('field_question')->getValue()[0]['value'];
      $report[] = [0 => $nid, 1 => $question_text];
    }

    dpm(json_encode($report));
    dpm('use https://www.convertcsv.com/json-to-csv.htm to convert to CSV');
  }


  /**
   * Returns the default options for the function getTotalQuizzes()
   * @todo break this out into its own smaller service
   *
   * @return array
   */
  public function getTotalQuizzesDefaultOptions(): array
  {
    $config = \Drupal::config('qwizard.qwizardsettings');
    $qtypes = $config->get('question_types');
    $defaults = [
      'course_id' => '',
      'topics' => [],
      'qwizes' => [],
      'class' => '',
      'question_ids' => [],
      'classes' => [],
      'editor_tags' => [],
      'count' => null,
      //'types' => ['qw_simple_choice'],
      'types' => $qtypes,
      'status' => 1,
      'cache' => 1,
      'force_flat_tags' => 0,
      'ignore_access_check' => 1,
    ];

    return $defaults;
  }


  /**
   * Given a set of parameters, gets valid qwiz nodes
   *
   * @param $params
   * @return array|int
   *
   * @todo Function getTotalQuizzes seems overly complicated, refactor.
   *       Also, name implies it will return quizzes, but returns question nids.
   */
  public function getTotalQuizzes($params){
    $QwCache = \Drupal::service('qwizard.cache');
    $defaults = $this->getTotalQuizzesDefaultOptions();
    $options = array_merge($defaults, $params);

    // Sort to help with consistent cache key ordering
    ksort($options, SORT_STRING);
    foreach($options as $key=>$value){
      if(is_array($value)){
        sort($options[$key]);
      }
    }

    // If 'classes' was given with only one result, change to 'class'
    // Helps with caching and forces '=' instead of 'IN' query
    if(!empty($options['classes']) && count($options['classes']) == 1){
      $options['class'] = reset($options['classes']);
      unset($options['classes']);
    }

    // Don't cache if looking for a specific question ID
    if(!empty($options['question_ids'])){
      $options['cache'] = 0;
    }

    // Cache key has a max size on it. Unset items for it to avoid hitting them
    $options_for_cache_key = $QwCache->get_options_for_cache_key($options);



    $cache_key = 'getTotalQuizzes_'.json_encode($options_for_cache_key);
    $cache = $QwCache->checkCache($cache_key);
    if($options['cache'] && !empty($cache)) {
      return $cache;
    }
    else {
      // Unsetting here to it doesn't affect cache names
      unset($options['cache']);

      $query = \Drupal::entityQuery('node');
      $storage = \Drupal::service('entity_type.manager')->getStorage('node');
      $query = $storage->getQuery();

      if(!empty($options['ignore_access_check'])){
        $query->accessCheck(false);
      }

      if(!empty($options['question_ids'])) {
        $query->condition('nid', $options['question_ids'], 'IN');
      }

      $op = 'IN';
      if(count($options['types']) == 1){
        $op = '=';
      }
      $query->condition('type', $options['types'], $op);

      // Send all in options to ignore status
      if($options['status'] != 'all') {
        $query->condition('status', $options['status'], '=');
      }

      // Editor Tags
      if (!empty($options['editor_tags'])) {
        $op = 'IN';
        if(count($options['editor_tags']) == 1){
          $op = '=';
        }
        $query->condition('field_editor_quiz_tags.entity.tid', $options['editor_tags'], $op);
      }


      // Class / Course / Topic tags
      if($options['force_flat_tags'] ||  !\Drupal::service('qwizard.general')->getStatics()['enable_paragraph_quiz_tagging']) {
        //Used for old method of counting with flat tags. Refactored in favor of paragraphs
        if (!empty($options['course_id'])) {
          $query->condition('field_courses.entity.tid', $options['course_id']);
        }
        if (!empty($options['class'])) {
          $query->condition('field_classes.entity.tid', $options['class'], '=');
        }
        if (!empty($options['classes'])) {
          $op = 'IN';
          if(count($options['classes']) == 1){
            $op = '=';
          }
          $query->condition('field_classes.entity.tid', $options['classes'], $op);
        }
        if (!empty($options['topics'])) {
          $op = 'IN';
          if(count($options['topics']) == 1){
            $op = '=';
          }
          $query->condition('field_topics.entity.tid', $options['topics'], $op);
        }
      }else{
        // To filter by a tag, we need to query against attached paragraphs
        if (!empty($options['course_id'])) {
          $query->condition('field_specified_topics.entity:paragraph.field_course.target_id', $options['course_id']);
        }
        if (!empty($options['class'])) {
          $query->condition('field_specified_topics.entity:paragraph.field_class.target_id', $options['class']);
        }
        if (!empty($options['classes'])) {
          $op = 'IN';
          if (count($options['classes']) == 1) {
            $op = '=';
          }
          $query->condition('field_specified_topics.entity:paragraph.field_class.target_id', $options['classes'], $op);
        }
        if (!empty($options['topics'])) {
          $op = 'IN';
          if (count($options['topics']) == 1) {
            $op = '=';
          }
          $query->condition('field_specified_topics.entity:paragraph.field_topic.target_id', $options['topics'], $op);
        }
      }

      if (!empty($options['question_body'])) {
        $query->condition('field_question.value', $options['question_body'], 'CONTAINS');
      }

      if (!empty($options['qwizes'])) {
        $op = 'IN';
        if(count($options['qwizes']) == 1){
          $op = '=';
        }
        $query->condition('nid', $options['qwizes'], $op);
      }
      if (!empty($options['count'])) {
        $query->addTag('sort_by_random');
        $query->range(0, $options['count']);
      }

      $data = $query->execute();

      if(!empty($options['cache'])) {
        $QwCache->setCacheFile($cache_key, $data);
      }

      return $data;
    }
  }

  public static function getPathInfo():array {
    $current_path = explode('?', \Drupal::request()->getRequestUri())[0];
    $path_args = explode('/', $current_path);

    return ['current' => $current_path, 'args' => $path_args];
  }

  /**
   * Short the string to fit $fit spaces.
   *
   * @param string $string
   *   String to make the fit.
   * @param int    $fit
   *   Ammount of characters allow.
   *
   * @return string
   *   Final string with shorten.
   */
  public static function shortNamesToFit(string $string, int $fit): string {
    if (strlen($string) < $fit) {
      return $string;
    }
    $string = trim(str_replace([
      " ",
      ",",
      "_",
      "-",
      ".",
      ",",
      ":",
    ], "", $string));
    if (strlen($string) < $fit) {
      return $string;
    }
    return substr($string, 0, $fit);
  }

  /**
   * Returns an array of topics in a given quiz, [$topic->id()] = $topic->label();
   * @param $class_id
   * @param $course_id
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getTopicsInQwiz($qwiz_id): array
  {
    $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $statics = self::getStatics();
    $banlist_topics = $statics['banlist_topics'];

    $topic_names = [];
    $topics_to_load = [];
    $current_qwiz = $qwiz_storage->load($qwiz_id);
    if(empty($current_qwiz)){
      return [];
    }
    $qwiz_topics = $current_qwiz->get('topics');

    foreach ($qwiz_topics as $topic) {
      $topic_id = $topic->getValue()['target_id'];
      if (!in_array($topic_id, $banlist_topics)) {
        $topics_to_load[] = $topic_id;
      }
    }

    $topics_loaded = $taxonomy_storage->loadMultiple($topics_to_load);
    foreach($topics_loaded as $topic){
      $topic_names[$topic->id()] = $topic->label();
    }

    return $topic_names;
  }

  /**
   * A tag string is a $course.'_'.$class.'_'.$topic combination
   * Generated from
   * @param $tag_string
   */
  public static function getQwizInfoFromTagString($tag_string){
    if(empty($tag_string) || $tag_string == '___'){
      return null;
    }
    $QwCache = \Drupal::service('qwizard.cache');
    $cache_key = 'getQwizInfoFromTagString_'.$tag_string;
    $cache = $QwCache->checkCache($cache_key, true);
    if(!empty($cache)) {
      return $cache;
    }


    \Drupal::service('qwizard.general')->getStatics();

    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $selected_topic_string_parts = explode('_', $tag_string);
    /*if($selected_topic_string_parts[2] == 'total'){
      return null;
    }
    if(empty($selected_topic_string_parts[2])){
      \Drupal::logger('qwgeneral')->notice('Error in getQwizInfoFromTagString, a string with topic missing was sent: '.json_encode($tag_string));
      return null;
    }
    $selected_topic = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($selected_topic_string_parts[2]);
    if(empty($selected_topic)){
      dpm('error in getQwizInfoFromTagString, on string '.$tag_string);
      return null;
    }
    $selected_topic_id = $selected_topic->id();
    */

    $selected_class_id = null;
    if(!empty($selected_topic_string_parts[1])){
      $selected_class = $taxonomy_storage->load($selected_topic_string_parts[1]);
      $selected_class_id = $selected_class->id();
    }


    $all_quizzes = \Drupal::entityTypeManager()->getStorage('qwiz')->loadMultiple();
    foreach ($all_quizzes as $quiz) {
      $test_tag_string = $tag_string;
      $qwiz_course = $quiz->getCourseId();
      $qwiz_class = $quiz->getClassId();
      if(empty($selected_class_id)){
        $qwiz_class = \Drupal::service('qwizard.general')->getStatics('study_classes')[$qwiz_course][0];
        $test_tag_string = str_replace('__', '_'.$qwiz_class.'_', $tag_string);
      }
      $qwiz_topics = $quiz->getTopicIds();

      // Force random topic into study/test classes, it's not attached to the quiz's but should be available
      if(in_array($qwiz_class, \Drupal::service('qwizard.general')->getStatics('class_tids_with_random_subtopic_allowed')) && empty($topics_by_class[$qwiz_class][224])){
        $qwiz_topics[] = 224;
      }

      foreach ($qwiz_topics as $qwiz_topic) {
        $topics_by_class[$qwiz_class][$qwiz_topic] = $quiz->id();
        if(empty($selected_topic_string_parts[2])){
          $qwiz_topic = null;
        }
        $qwiz_tag_string = $qwiz_course.'_'.$qwiz_class.'_'.$qwiz_topic;

        if($qwiz_tag_string == $test_tag_string) {
          $is_primary_course = false;
          if(in_array($qwiz_class, \Drupal::service('qwizard.general')->getStatics('study_test_classes'))){
            $is_primary_course = true;
          }
          $return_data = [
            'qwiz' => $quiz->id(),
            'course' => $qwiz_course,
            'class' => $qwiz_class,
            'topic' => $qwiz_topic,
            'is_primary_course' => $is_primary_course,
          ];
          $QwCache->setCacheFile($cache_key, $return_data, true);
          return $return_data;
        }
      }
    }

    return null;
  }

  /**
   * This function is an attempt to centralize some hardcoded general data
   * If any of this is made dynamic, be sure it is still performant. Or at least cached
   * $statics = QwizardGeneral::getStatics();
   * @todo this would benefit highly from being its own class, if any values are made dynamic in the future
   *
   * @param null $key
   * @return mixed
   */
  public static function getStatics($key = null)
  {
    $statics = [];
    $statics['all_courses'] = [200 => 'NAVLE', 201 => 'BCSE', 202 => 'VTNE'];
    $statics['course_title_prefixes'] = [200 => 'NAVLE®', 201 => 'Clinical Sciences', 202 => 'VTNE'];
    $statics['course_menu_titles'] = [200 => 'NAVLE®', 201 => 'BCSE/PAVE', 202 => 'VTNE'];
    $statics['org_courses_sequence'] = [200 => 'NAVLE', 202 => 'VTNE', 201 => 'BCSE'];
    $statics['test_classes'] = [200 => [460], 201 => [461], 202 => [462]];
    $statics['test_class_ids'] = [460, 461, 462];
    $statics['study_classes'] = [200 => [185], 201 => [188], 202 => [191]];
    $statics['study_test_classes'] = [460, 461, 462, 185, 188, 191];
    $statics['multiquiz_quizzes'] = [200 => 2, 201 => 79, 202 => 21];
    $statics['secondary_timed_test_classes'] = [200 => [477], 201 => [476], 202 => [477]];
    $statics['secondary_classes_with_study_topics'] = [476, 477, 474, 582, 593, 463, 464, 675, 687, 690];
    $statics['test_mode_classes'] = [460, 461, 462];
    $statics['banlist_topics'] = [224]; //Random
    $statics['college_classes'] = [675];
    $statics['ohio_class_id'] = 675;
    $statics['ohio_quiz_id'] = 150;
    $statics['class_tids_with_random_subtopic_allowed'] = array_merge($statics['study_test_classes'], $statics['secondary_classes_with_study_topics']);
    $statics['banlist_topic_names'] = ['Random']; //224
    $statics['course_roles'] = ['bcse' => 'BCSE', 'navle' => 'NAVLE', 'vtne' => 'VTNE'];
    $statics['prem_roles'] = ['bcse_premium' => 'bcse_premium', 'navle_premium' => 'navle_premium'];

    $statics['extension_default_limit'] = 2;
    $statics['subscription_expiration_time_limit'] = '10 days ago';
    $statics['enable_paragraph_quiz_tagging'] = 1;
    $statics['enable_json_cache'] = 1;
    $statics['enable_snapshots_from_vid'] = 0;
    $statics['full_course_roles'] = [
      200 => [
        'normal' => 'navle',
        'premium' => 'navle_premium',
      ],
      201 => [
        'normal' => 'bcse',
        'premium' => 'bcse_premium',
      ],
      202 => [
        'normal' => 'vtne',
        'premium' => null,
      ],
    ];

    if(!empty($key) && !empty($statics[$key])){
      return $statics[$key];
    }else{
      return $statics;
    }
  }

  /**
   * Helper function, used to determine class type
   *
   * @param $class_id
   * @param $course_id
   * @return string
   */
  public static function getClassType($class_id, $course_id): string
  {
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $statics = $QwizardGeneral->getStatics();
    $test_classes = $statics['test_classes'];
    $study_classes = $statics['study_classes'];
    $secondary_study_classes = $statics['class_tids_with_random_subtopic_allowed'];
    $college_class_ids = $statics['college_classes'];
    // $secondary_study_classes = $statics['study_test_classes'];

    // VTNE Readiness 2 does not support topic breakdown
    // @todo: What does 'VTNE Readiness 2 does not support topic breakdown' mean here?
    /*
     if($class_id == 593){
        return 'other';
    }
    */

    $class_type = 'other';
    if(empty($class_id)){
      $class_type = 'empty';
    }
    if(in_array($class_id, $college_class_ids)){
      $class_type = 'college_study';
    }
    elseif(in_array($class_id, $test_classes[$course_id])) {
      $class_type = 'test_mode';
    }
    elseif(in_array($class_id, $study_classes[$course_id])) {
      $class_type = 'study_mode';
    }
    elseif(in_array($class_id, $secondary_study_classes)){
      $class_type = 'secondary_study';
    }

    return $class_type;
  }

  function rebuildResultsForUser($user_id = null, $in_background = true){
    if(empty($user_id)){
      $user_id = \Drupal::currentUser()->id();
    }

    if($in_background){
      $queue = \Drupal::queue('qwmaintenance_queue');
      $queue->createQueue();
      $item      = new \stdClass();
      $item->uid = $user_id;
      $item->operations = ['Rebuild Results'];
      $queue->createItem($item);
    }else{
      $qwMaintenancePools = new QWMaintenancePoolsOneUser;
      $qwMaintenancePools->rebuildPools($this->currentUser->id(), false, true, FALSE, false, true);
    }
  }

  /**
   * Get classes for course.
   */
  public function getClassesForCourse($course_id, $loaded = FALSE) {
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $query = $term_storage->getQuery();
    $query->condition('vid', 'classes');
    $query->condition('status', 1);
    $query->condition('field_course', $course_id);
    $query->sort('weight');
    $ids = $query->execute();
    $terms = $term_storage->loadMultiple($ids);
    if ($loaded) {
      return $terms;
    }
    else {
      $classes = [];
      foreach ($terms as $term) {
        $classes[$term->id()] = $term->getName();
      }
      return $classes;
    }
  }
}
