<?php

namespace Drupal\qwreporting;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Session\AccountInterface;
use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\Entity\Qwiz;
use Drupal\qwizard\Entity\QwPool;
use Drupal\taxonomy\Entity\Term;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\qwizard\QwStudentResultsHandler;
use Drupal\user\Entity\User;

/**
 * Access Students in the context of a group.
 */
class QwreportingStudents implements StudentsInterface {

  /**
   * {@inheritdoc}
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getStudents(int $group_id, $selected_topic = NULL, $since = NULL, $end = NULL, $selected_class_id = NULL, $selected_qwiz_id = NULL, $inactive_subscription = FALSE): array {
    $array = [];
    $group = Term::load($group_id);

    if (!$group->hasField('field_students')) {
      return [];
    }
    if(!$group->hasField('field_course')){
      Throw new \Exception($group_id.' Term has no course, fix at /taxonomy/term/'.$group_id.'/edit');
    }

    $ids = array_column($group->field_students->getValue(), 'target_id');
    $QWGeneral = \Drupal::service('qwizard.general');

    // warm up entity cache for results on all these students to speed up future queries
    $query   = \Drupal::entityQuery('qw_student_results')
      ->condition('user_id', $ids, 'IN');
    $query->condition('status', 1);
    $query->condition('course', $group->field_course->target_id);
    $results = $query->execute();
    $warmed_cache_results = \Drupal::entityTypeManager()->getStorage('qw_student_results')->loadMultiple($results);


    // BUG - Sometimes a student may have duplicate active results. In this case grab the most recent one
    // Can be removed if AutoFixers.php is run on cron


    $users = User::loadMultiple($ids);
    foreach ($users as $user) {
      // Combine first & last name
      $combined_name = '';
      if(!empty($user->field_last_name->value)){
        $combined_name .= $user->field_last_name->value;
        if(!empty($user->field_first_name->value)){
          $combined_name .= ', ';
        }
      }
      if(!empty($user->field_first_name->value)){
        $combined_name .= $user->field_first_name->value;
      }

      $data = $this->getGeneralData($user, $group->field_course->target_id, true, $selected_topic, $since, $end, $inactive_subscription);

      //  ZUKU-1314 - if we're on a test/study mode page, also grab EVERY other topic
      $other_topics = [];
      if(!empty($selected_qwiz_id) && !empty($selected_class_id) && empty($selected_topic) && in_array($selected_class_id, $QWGeneral->getStatics('study_test_classes'))){
        // @todo get topics better, this is VTNE locked

        $course_id = $group->field_course->target_id;
        $QwCache = \Drupal::service('qwizard.cache');
        $quiz_cache_key = 'quizResultsCache_' . $course_id . '_' . $selected_class_id;
        $cache = $QwCache->checkCache($quiz_cache_key);
        if (empty($cache['topic_results']) || empty ($cache['questions_with_topics'])) {
          $cache = $QwCache->buildClassCache($course_id, $selected_class_id);
        }
        $topics_in_quiz = $cache['topic_results'];

        //$topics_in_quiz = array_keys($QWGeneral->getTopicsInQwiz(21));
        //$topic_tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($topics_in_quiz);

        foreach($topics_in_quiz as $topic_id=>$topic_info){
          if($topic_info['label'] == 'Random') continue;
          $other_topics[$topic_id] =  $this->getGeneralData($user, $group->field_course->target_id, true, $topic_id, $since, $end, $inactive_subscription);
          $other_topics[$topic_id]['label'] = $topic_info['label'];

          // If we're looking at a test mode, slap its data on top so the template renders it
          if(in_array($selected_class_id, $QWGeneral->getStatics('test_class_ids'))){
            $other_topics[$topic_id] = array_merge($other_topics[$topic_id], $other_topics[$topic_id]['test_mode']);
          }
        }
      }

      $array[] = [
        "name" => $user->getDisplayName(),
        "email" => $user->getEmail(),
        'last_access' => $user->getLastAccessedTime(),// This version is only used by excel
        "combined_name" => $combined_name,
        "id" => $user->id(),
        "data" => $data,
        "other_topics" => $other_topics,
      ];
    }
    // Sort by combined name
    uasort($array, function($a, $b)
    {
      return strcmp($a['combined_name'], $b['combined_name']);
    });

    #dpm($array);
    return $array;
  }

  private function formatLastAccess($time): string
  {
    $string = 'never';
    if(!empty($time)){
      $string = \Drupal::service('date.formatter')->formatTimeDiffSince($time);
    }
    return $string;
  }

  /**
   * Used for detailed reporting on a single student
   * {@inheritdoc}
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public  function getStudentData($course, $student, $include_snapshots = FALSE, $specific_subscription = NULL, $since = NULL, $inactive_subscription = FALSE):array {
    $details = $this->getData($student, $course, FALSE, NULL, $specific_subscription, $since, NULL, $inactive_subscription);
    $subscription = \Drupal::entityTypeManager()->getStorage('subscription')->load($details['subscription_id']);


    $study_test_classes = \Drupal::service('qwizard.general')->getStatics('study_test_classes');
    $test_mode_classes = \Drupal::service('qwizard.general')->getStatics('test_mode_classes');
    $college_classes = \Drupal::service('qwizard.general')->getStatics('college_classes');
    $subtopic_classes = \Drupal::service('qwizard.general')->getStatics('class_tids_with_random_subtopic_allowed');
    $snapshots_to_add = [];
    $snapshots_qwizzes_to_load = [];

    // Load all classes at once
    $class_ids = [];
    foreach ($details['data'] as $class_id => $data) {
      if(!empty($data['class'])) {
        $class_ids[] = $data['class'];
      }
    }
    $classes = Term::loadMultiple($class_ids);

    foreach ($details['data'] as $class_id => $data) {
      if(empty($class_id)) continue;
      if ($data['class'] == NULL) continue;

      $class_term = $classes[$data['class']];
      $details['data'][$class_id]['classes'] = $this->getDetailsOfClass($class_term);
      $qwiz_id = null;

      foreach($details['data'][$class_id]['classes']['qwizzes'] as $qwiz_id=>$qwiz_data){
        $snapshots_qwizzes_to_load[$qwiz_id] = $qwiz_id;
        $snapshots_to_add[$class_id]['topics'][$qwiz_id] = $qwiz_id;
      }
    }

    // This whole section is used to load all snapshots in a single query
    if ($include_snapshots && !empty($snapshots_qwizzes_to_load)) {
      //Get all the results and organize them by qwiz_id
      $snapshots_by_id = $this->getSessionInformation($student, $subscription, array_keys($snapshots_qwizzes_to_load));
      $snapshots_by_qwiz_id = [];
      $snapshots_by_class_id = [];
      $snapshots_by_class = [];

      foreach($snapshots_by_id as $result_id => $result) {
        if (empty($result)) continue;
        $qwiz_id = $result['qwiz_id'];
        $qwiz = \Drupal::entityTypeManager()->getStorage('qwiz')->load($qwiz_id);
        $qwiz_class = $qwiz->getClassId();
        $snapshots_by_qwiz_id[$qwiz_id][$result_id] = $result;
        $snapshots_by_class_id[$qwiz_class][$result_id] = $result;
        $snapshots_by_class[$qwiz_class][$result_id] = $result;
        $topics = $qwiz->getTopicIds();
        foreach($topics as $topic_id) {
          $snapshots_by_class_and_topic_id[$qwiz_class][$topic_id][$result_id] = $result;
        }
      }

      // Actually include the snapshots into results now that they're loaded
      foreach($details['data'] as $class_id=>$class_data){
        if(empty($class_id)) continue;
        foreach($details['data'][$class_id]['results'] as $qwiz_id=>$qwiz_data){
          if(!is_int($qwiz_id) && $qwiz_id != 'total'){
            echo '!!qwiz_id in getStudentData needs to be an int here!! '; var_dump($class_id); var_dump($details['data'][$class_id]['results']); exit;
          }
          // Test classes get special attachment for titles.
          if((in_array($class_id, $college_classes) || in_array($class_id, $test_mode_classes)) && !empty($details['data'][$class_id]['results']['total']) && !empty($snapshots_by_class[$class_id])){
            $details['data'][$class_id]['results']['total']['snapshots'] = $snapshots_by_class[$class_id];
          }
          elseif(!empty($snapshots_by_qwiz_id[$qwiz_id])) {
            $details['data'][$class_id]['results'][$qwiz_id]['snapshots'] = $snapshots_by_qwiz_id[$qwiz_id];
          }

          // Secondary topic tests are having trouble getting attached.
          // @todo Sometimes qwiz_id is a topic id. Just use that if available.
          //   What? Does this mean classes with one topic or quiz?
          elseif(!empty($snapshots_by_class_and_topic_id[$class_id][$qwiz_id])) {
            $details['data'][$class_id]['results'][$qwiz_id]['snapshots'] = $snapshots_by_class_and_topic_id[$class_id][$qwiz_id];
          }
          else{
            $details['data'][$class_id]['results'][$qwiz_id]['snapshots'] = [];
          }

        }
      }
    }

    // Sorting, throw study_test_classes at the top
    $study_test_classes = \Drupal::service('qwizard.general')->getStatics('class_tids_with_random_subtopic_allowed');
    if(!empty($details['data'][0])) unset($details['data'][0]);
    if(!empty($details['data'][1])) {
      var_dump('error, data array is not keyed by class_id'); exit;
    };
    $sorted_classes = [];
    foreach($study_test_classes as $class_id){
      if(empty($details['data'][$class_id])) continue;
      $class_data = $details['data'][$class_id];
      if(in_array($class_id, $subtopic_classes)){
        $sorted_classes[$class_id] = $class_data;
        unset($details['data'][$class_id]);
      }
    }
    // array_reverse is to get study mode at the top, code is to preserve keys
    $keys = array_keys($sorted_classes);
    $vals = array_reverse(array_values($sorted_classes));
    foreach ($vals as $k => $v) {
      $sorted_classes[$v['class']] = $v;
    }
    foreach(array_reverse($details['data']) as $class_id=>$class_data){
      $sorted_classes[$class_data['class']] = $class_data;
    }
    $details['data'] = $sorted_classes;

    if(!empty($details['data'][1])) {
      var_dump('error, data array is not keyed by class_id');
      var_dump(array_keys($details['data']));
      exit;
    };

    // If results is empty due to no sessions, populate it from class data
    foreach($details['data'] as $class_id=>$class_data){
      if(in_array($class_id, $college_classes)) {
        // Can't on college classes or their real topic will show up.
        continue;
      }
      $details['data'][$class_id]['classes']['class_id'] = $class_id;
      foreach($class_data['classes']['qwizzes'] as $topic_id=>$topic_data){
        // Find the class based on ID's
        $matching_class = $class_data['classes']['qwizzes'][$topic_id];
        $class_qr_name = $matching_class['name'];
        if(empty($class_data['results'][$matching_class['id']]) && !in_array($class_id, $subtopic_classes)){
          //prepopulate it

          unset($details['data'][$class_id]['results'][$topic_id]);
          $details['data'][$class_id]['results'][$matching_class['id']] = [
            'label' => $class_qr_name,
            'id' => $matching_class['id'],
            'total_questions' => $matching_class['total_questions'],
            'seen' => 0,
            'attempted' => 0,
            'correct' => 0,
            'score_all' => 0,
            'score_seen' => 0,
            'score_attempted' => 0,
            'snapshots' => [],
            'was_empty' => true,
          ];
        }
      }
    }

    return $details;
  }

  public function getSessionInformation(AccountInterface $student, $subscription, $qwiz_ids){
    $rest_service = \Drupal::service('qwrest.results');

    $sessions = $rest_service->getResultData($student, $subscription, $qwiz_ids, null, array(), false);
    if(!empty($sessions['results_list'])){
      $sessions = $sessions['results_list'];
    }

    return $sessions;
  }

  /**
   * Get Class details including Qwiz object.
   *
   * @param int $class
   *   Id of class's Taxonomy.
   *
   * @return array
   *   Empty or with name, description and quizzes.
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getDetailsOfClass($class_taxonomy): array {
    $class_properties = [];
    if ($class_taxonomy) {
      $qwiz_storage       = \Drupal::entityTypeManager()
        ->getStorage('qwiz');

      $QWGeneral = \Drupal::service('qwizard.general');
      $study_test_classes = $QWGeneral->getStatics('study_test_classes');

      $query       = \Drupal::entityQuery('qwiz')
        ->condition('class', $class_taxonomy->id())
        ->condition('status', 1);
      $result      = $query->execute();
      $qwizzes     = $qwiz_storage->loadMultiple($result);

      $qwizzes_in_class = [];
      $topics_in_qwiz = [];
      foreach ($qwizzes as $qwizz) {
        $qwizz_array= [
          'id' => $qwizz->id(),
          'name' => $qwizz->getName(),
        ];

        // Get total qwiz questions for the qwiz
        // Leaving enabled flag, but unsure if this is necessary
        $enabled = false;
        if(!in_array($class_taxonomy->id(), $study_test_classes)){
          $enabled = true;
        }
        if($enabled) {
          $params = [];
          $class = $qwizz->get('class')->getValue();
          if (!empty($class)) {
            $class = array_column($class, 'target_id');
            $params['class'] = reset($class);
          }

          $topics = $qwizz->get('topics')->getValue();
          if (!empty($topics)) {
            $topics = array_column($topics, 'target_id');
            foreach($topics as $topic) {
              $topics_in_qwiz[$topic] = $topic;
            }
            $params['topics'] = $topics;
          }

          $total_qwizzes = $QWGeneral->getTotalQuizzes($params);
          $qwizz_array['total_questions'] = empty($total_qwizzes) ? 0 : count($total_qwizzes);
        }
        $qwizzes_in_class[$qwizz->id()] = $qwizz_array;
      }

      $class_description = $class_taxonomy->getDescription();
      $class_properties = [
        "name" => $class_taxonomy->getName(),
        "description" => $class_description,
        "qwizzes" => $qwizzes_in_class,
        "topics" => $topics_in_qwiz,
      ];
      if (!empty($class_description)) {
        $class_properties['description_processed'] = [
          '#type' => 'processed_text',
          '#text' => $class_description,
          '#format' => $class_taxonomy->getFormat(),
        ];
      }
    }

    return $class_properties;
  }

  /**
   * Used to get extra details for one user in a group
   * @param $user
   * @param $course_id
   * @param bool $totals_only
   * @param null $selected_topic
   * @return array
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getGeneralData($user, $course_id, bool $totals_only = TRUE, $selected_topic = NULL, $since = NULL, $end = NULL, $inactive_subscription = FALSE): array
  {
    $data = $this->getData($user, $course_id, $totals_only, $selected_topic, NULL, $since, $end, $inactive_subscription);

    $results = array_column($data['data'], 'results');
    $results_by_class = [];
    foreach($data['data'] as $class_id=>$class_results){
      $results_by_class[$class_id] = $class_results['results'];
    }

    $last_access = 0;
    if(!empty($data['last_access'])){
      $last_access = $data['last_access'];
    }

    $final = [
      "last_access" => $last_access,
      "overallProgress" => 0,
      "attempted" => 0,
      "correct" => 0,
      "totalScore" => 0,
      "totalQuestion" => 0,
      "avg" => 0,
      'test_mode' => []
    ];
    if(!$data['subscription_active']){
      if (!empty($data['subscription_id'])) {
        // Just display the message & not last access timestamp.
        $final["last_access"] = '<span style="color:red">'.t('Subscription has expired.').'</span>';
      }
      else {
        $final["last_access"] .= '<br /><span style="color:red">'.t('User is not currently subscribed to this course.').'</span>';
      }
    }

    $aggregates = [];
    if (!empty($results)) {
      $QWGeneral = \Drupal::service('qwizard.general');
      $test_classes = $QWGeneral->getStatics('test_classes'); //[200 => [460], 201 => [461], 202 => [462]];
      $study_classes = $QWGeneral->getStatics('study_classes'); //[200 => [185], 201 => [188], 202 => [191]];
      $secondary_classes_with_topics = array_merge([$QWGeneral->getStatics('college_classes')], $QWGeneral->getStatics('secondary_classes_with_study_topics'));
      $test_results = [];
      $study_results = [];
      $secondary_results = [];

      foreach($results_by_class as $result_class_id=>$value) {
        #foreach ($results as $key => $value) {
        if (empty($value) ) {
          #unset($results[$key]);
          continue;
        }

        foreach($value as $key=>$class_result) {
          if(!empty($selected_topic) && $class_result['id'] != $selected_topic && in_array($result_class_id,  \Drupal::service('qwizard.general')->getStatics('study_test_classes'))){
            unset($value[$key]);
          }
        }

        if (in_array($result_class_id, $test_classes[$course_id])) {
          $test_results = $this->getGeneralResultData($value, $totals_only);
        }
        elseif (in_array($result_class_id, $study_classes[$course_id])) {
          $study_results = $this->getGeneralResultData($value, $totals_only);
        }
        elseif (in_array($result_class_id, $secondary_classes_with_topics)) {
          foreach($value as $topic_id => $class_result){
            // Have to use this instead of reading off total. Ohio is unique.
            // @todo remove ohio hard code. Needs to be good for any group class
            if(in_array($topic_id, $QWGeneral->getStatics('college_classes')) &&
              \Drupal::routeMatch()->getRouteName() != 'qwreporting.results.individual') {
              $class_result['id'] = $QWGeneral->getStatics('ohio_quiz_id');
            }

            $qwiz = \Drupal::entityTypeManager()->getStorage('qwiz')->load($class_result['id']);
            // Sometimes id is a topic, sometimes it's a qwiz. fun.
            //if ($class_result['id'] == 'total') continue;
            if (empty($qwiz)) {
              $qwiz_id = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($course_id . '_' . $result_class_id . '_' . str_replace('total', '', $class_result['id']));
              if (!empty($qwiz_id)) {
                $secondary_results[$result_class_id][$qwiz_id['qwiz']] = $this->getGeneralResultData([$class_result], $totals_only);
              }
            }
            else {
              // This assumes each subtest only has 1 topic
              $qwiz_topics = $qwiz->getTopicIds();
              $secondary_results[$result_class_id][$qwiz_topics[0]] = $this->getGeneralResultData([$class_result], $totals_only);
            }
          }
        }
        else {
          $first_value = reset($value);
          $quiz = null;
          if (!empty($first_value['id'])) {
            if ($result_class_id == 'total') continue;
            $result_quiz_id = $first_value['id'];
            $quiz = \Drupal::entityTypeManager()->getStorage('qwiz')->load($result_quiz_id);
            if (empty($quiz)) continue;
            //$result_class_id = $quiz->get('class')->getValue()[0]['target_id'];
          }
          if (!empty($first_value['label']) && !empty($quiz)) {
            #$secondary_results[$result_class_id] = $this->getGeneralResultData($value, $totals_only, $quiz);
            $secondary_results[$result_class_id] = $value;
          }
        }
        #}
      }

      $tmp_final = $final;
      $final = array_merge($tmp_final, $study_results);
      $final['test_mode'] = array_merge($tmp_final, $test_results);
      $final['secondary'] = $secondary_results;

      // ZUKU-1314 - if we're on a test/study mode page, also grab EVERY other topic
      //in_array($result_class_id,  \Drupal::service('qwizard.general')->getStatics('study_test_classes'))

      unset($final['test_mode']['test_mode']);
    }

    #dpm($results);
    if(!empty($aggregates)){
      #dpm($aggregates);
      #dpm($final);
    }
    #dpm($final);
    #var_dump($final); exit;
    return $final;
  }

  /**
   * @param $results
   * @param $data
   * @param $totals_only
   * @return array|null
   */
  public function getGeneralResultData($results, $totals_only, $quiz = null): array   {
    $final = [];
    if (empty($results)) return $final;
    if ($totals_only) {
      $aggregates = $this->sumAllResults($results);
    }
    else{
      $aggregates = [$results];
    }

    if(empty($aggregates)){
      // If no summed results, Just use the default final array above, skip processing
      return $final;
    }

    // Rename variables for qw_reporting templates, and get extra data
    $final["avg"] = $this->calculateAvgCorrect($aggregates);
    $final['qwiz_id'] = $aggregates['id'];

    $final["overallProgress"] = $this->calculateOverallProgress($aggregates, $aggregates['total_questions']);
    // @todo: figure out why these conditions sometimes exists and fix it right.
    /*if ($aggregates['attempted'] > $aggregates['total_questions']) {
      $aggregates['attempted'] = $aggregates['total_questions'];
    }*/
    if ($aggregates['seen'] > $aggregates['total_questions']) {
      $aggregates['seen'] = $aggregates['total_questions'];
    }
    if ($aggregates['correct'] > $aggregates['total_questions']) {
      $aggregates['correct'] = $aggregates['total_questions'];
    }
    if ($final['overallProgress'] > 100) {
      $final['overallProgress'] = 100;
    }

    // For fields that just need a basic to->from mapping
    $simple_fields = [
      'attempted' => 'attempted',
      'correct' => 'correct',
      'actually_correct' => 'actually_correct',
      'totalScore' => 'score_all',
      'totalQuestion' => 'total_questions',
    ];
    foreach($simple_fields as $template_name=>$result_name){
      if(isset($aggregates[$result_name])){
        $final[$template_name] = $aggregates[$result_name];
      }
    }
    if(empty($final['actually_correct'])){
      $final['actually_correct'] = 'correct';
    }
    // Convert totalScore to a percentage for twig
    if(!empty($final['totalScore'])) {
      $final['totalScore'] = round($final['totalScore'], 1) * 100 . '%';
    }
    $final['overallProgress'] = round($final['overallProgress'], 1) . '%';

    #dpm($aggregates);
    #if(empty($final['totalQuestion'])){
    if(!empty($quiz)){
      $QwizardGeneral = \Drupal::service('qwizard.general');
      $topic_ids = array_keys($QwizardGeneral->getTopicsInQwiz($quiz->id()));

      $params = ['class' => $quiz->getClassId()];
      if (!empty($topic_ids)) {
        $params['topics'] = $topic_ids;
      }
      $secondary_total = count($QwizardGeneral->getTotalQuizzes($params));
      $final['totalQuestion']  = $secondary_total;
    }

    #dpm($final);
    return $final;
  }

  /**
   * Sum all results by key provided.
   * Gets down into individual results, combine them into one array for all classes
   *
   * @param array $results
   *   All the results in an array (key, value)
   *
   * @return array
   *   Results summed by key. A normal results array with total_questions and score_seen, keyed at the start sumAllResults([$first_result_group, $second_result_group])
   */
  public function sumAllResults(array $results):array {
    $final = [];
    // We have to go deeper
    foreach ($results as $res) {
      if (!empty($res) && count($res) > 0) {
        foreach ($res as $key => $value) {
          $key = strtolower($key);
          if($key == 'id'){
            $final[$key] = $value;
          }
          // Combine fields into final array, copying keys
          if(!isset($final[$key])){
            $final[$key] = $value;
          }else{
            // Add up numbers for each result into combined array
            if(is_numeric($value)){
              $final[$key] = $final[$key] + $value;
            }
          }
        }
      }
    }

    return $final;
  }

  /**
   * Get the date of last update sorting inside an array.
   *
   * @param array $data
   *   Data that includes the key value 'updated'.
   *
   * @return mixed|null
   */
  public function getLastDateUpdate(array $data) {
    $updated = array_column($data, 'updated');
    $final = NULL;
    foreach ($updated as $date) {
      if ($date > $final) {
        $final = $date;
      }
    }
    return $final;
  }

  /**
   * Calculate the progress of a student in the course.
   *
   * @param array $aggregates
   *   Aggregates data from user.
   * @param int $total_questions
   *   Ammount of questions of the course.
   *
   * @return float|int
   *   Progress percentage.
   */
  public function calculateOverallProgress(array $aggregates, int $total_questions) {

    $total_correct = 0;
    if(!empty($aggregates['correct'])) {
      $total_correct = $aggregates['correct'];
    }

    return empty($total_questions) ? 0 : round($total_correct / $total_questions * 100, 2);
  }

  /**
   * Get the correct percentage between attepted questions and correct ones.
   *
   * @param array $aggregates
   *   Array with totals of students.
   *
   * @return float
   *   Correctness percentage.
   */
  public function calculateAvgCorrect(array $aggregates):float {
    if(!empty($aggregates['attempted']) && !empty($aggregates['correct'])) {
      if(!empty($aggregates['actually_correct'])) $aggregates['correct'] = $aggregates['actually_correct'];
      return round($aggregates['correct'] / $aggregates['attempted'] * 100, 2);
    }else{
      return 0;
    }
  }

  /**
   * Get data from User in the context of a group.
   * Used for individual details
   *
   * @param \Drupal\user\Entity\User $user
   *   User object.
   * @param int $group
   *   Taxonomy id of group.
   * @param bool $totals_only
   * @param null $selected_topic
   * @return array
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getData(User $user, int $group, bool $totals_only = TRUE, $selected_topic = NULL, $specific_subscription = NULL, $since = NULL, $end = NULL, $inactive_subscription = FALSE):array {
    $final                    = [
      'total_questions' => 0,
      'email' => '',
      'username' => '',
      'uid' => '',
      'courseid' => '',
      'coursename' => '',
      'subscription_active' => 0,
      'name' => '',
      'data' => []

    ];
    $QWGeneral = \Drupal::service('qwizard.general');
    $study_test_classes = \Drupal::service('qwizard.general')->getStatics('study_test_classes');
    $statics_subtopics = \Drupal::service('qwizard.general')->getStatics('secondary_classes_with_study_topics');
    $student_results_handler = \Drupal::service('qwizard.student_results_handler');
    $courseObject             = Term::load($group);
    $final['courseid'] = $courseObject->id();
    $final['coursename'] = $courseObject->getName();
    $final['uid'] = $user->id();
    $final['total_questions'] = count($QWGeneral->getAllQuestionIdsForCourse($courseObject));
    $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');


    $is_specific_sub = false;
    if(!empty($specific_subscription)){
      $is_specific_sub = true;
      $subscription = \Drupal::entityTypeManager()->getStorage('subscription')->load($specific_subscription);
    }
    else {
      $subscription = $subscriptions_service->getCurrentSubscription($courseObject, $user->id(), NULL, $inactive_subscription);
    }

    if (!empty($subscription)) {
      $final['subscription_active'] = $subscription->status->value;
      $final['subscription_id'] = $subscription->id();

      if(!empty($since) || !empty($end)) {
        // Dynamically calculate results, using sessions in the time period
        $student_results = $this->calculateResultsForTimePeriod($user, $subscription, $since, $end);
      }
      else {
        $sResults = $student_results_handler->getStudentResults($user, $subscription, null, $is_specific_sub);
        $srStorage       = \Drupal::entityTypeManager()
          ->getStorage('qw_student_results');
        $student_results = $srStorage->loadMultiple($sResults);
        #dpm($student_results);
      }



      foreach ($student_results as $studentResult) {
        $array_key = $studentResult->getClassId();
        if(empty($array_key)) $array_key = '0';

        $final['data'][$array_key] = [
          'srid'            => $studentResult->id(),
          'name'            => $studentResult->name->value,
          'subscription_id' => $studentResult->getSubscriptionId(),
          'course'          => $studentResult->getCourseId(),
          'class'           => $studentResult->getClassId(),
          'updated'         => $QWGeneral->formatIsoDate($studentResult->changed->value),
          'results'         => $studentResult->getResultsJson('array_keyed_by_qwiz_id'),
        ];


        // Validate result data. unset if it is not usable here.
        // Most likely the qw_student_result just needs a refresh, or that this controller is unable to handle a different format
        // Only run on test/study classes
        if(false && in_array($array_key, $statics_subtopics)) {
          if(!empty($final['data'][$array_key]['results']) && !empty($final['data'][$array_key]['class'])){
            foreach($final['data'][$array_key]['results'] as $key=>$result_data){
              if(!empty($final['data'][$array_key]['results'][$key]['label'])) {
                // force a correct total
                #$final['data'][$array_key]['results'][$key]['total_questions'] = $QWGeneral->getTotalQuizzes($params);
                #$final['data'][$array_key]['results'][$key] = $final['data'][$array_key]['results'][$key];
                #unset($final['data'][$array_key]['results'][$key]);
                #$final['data'][$array_key]['results'][$key]['label'] = str_replace(['-', $studentResult->getClass()->label()], '', $final['data'][$array_key]['results'][$key]['label']);
              }
            }
          }
        }
        else {
          if (empty($final['data'][$array_key]['results']['total']) || !isset($final['data'][$array_key]['results']['total']['total_questions'])) {
            $final['data'][$array_key]['results'] = [];
          }
          else {
            if ($totals_only) {
              $topic = 'total';
              if (!empty($selected_topic) && !empty($final['data'][$array_key]['results'][$selected_topic])) {
                $topic = $selected_topic;
              }

              $total_result = $final['data'][$array_key]['results'][$topic];
              $final['data'][$array_key]['results'] = [];
              $final['data'][$array_key]['results'][$array_key] = $total_result;
            } else {
              // Return all courses, not just the totals. Remove total from array
              unset($final['data'][$studentResult->id()]['results']['Total']);
            }
          }
        }

        if(empty($final['data'][$array_key]['results'])){
          $final['data'][$array_key]['results'] = $studentResult->getResultsJson('array_keyed_by_qwiz_id');
        }
      }

      $final['email'] = $user->getEmail();
      $final['username'] = $user->getAccountName();
      $final['real_name'] = $user->field_first_name->value . ' ' . $user->field_last_name->value;
      $final['last_access'] = self::formatLastAccess($user->getLastAccessedTime());
    }

    #dpm($final);
    return $final;
  }

  public function calculateResultsForTimePeriod($user, $subscription, $since, $end){
    $results = [];
    // Get sessions
    $qwiz_result_storage = \Drupal::entityTypeManager()
      ->getStorage('qwiz_result');
    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('subscription_id', $subscription->id())
      ->condition('user_id', $user->id());

    if(!empty($since) && !empty($end)){
      $query->condition('created', [$since, $end], 'BETWEEN');
    }
    elseif(!empty($since)) {
      $query->condition('created', $since, '>=');
    }elseif(!empty($end)){
      $query->condition('created', $end, '<=');
    }

    $sessions_ids_in_time_period = $query->execute();

    if(empty($sessions_ids_in_time_period)){
      return $results;
    }
    $sessions_in_time_period = $qwiz_result_storage->loadMultiple($sessions_ids_in_time_period);

    $sessions_by_class = [];
    foreach($sessions_in_time_period as $session){
      $class_id = $session->get('class')->getValue()[0]['target_id'];
      $sessions_by_class[$class_id][$session->id()] = $session;
    }




    // Create unsaved qw_student_results entities
    $init_results = QwStudentResultsHandler::initStudentResults($user, $subscription, false);
    foreach($init_results as $result){
      if(empty($result->get('class')->getValue())) continue;

      $class_id = $result->get('class')->getValue()[0]['target_id'];
      // Score Sessions
      if(!empty($sessions_by_class[$class_id])) {
        $result->only_rebuild_with_set_results = true;
        $result->setResultsToRebuildWith($sessions_by_class[$class_id]);
      }
      $result->rebuildResults();

      $results[] = $result;
    }

    return $results;
  }
}
