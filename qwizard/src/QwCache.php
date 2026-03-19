<?php

namespace Drupal\qwizard;

use Drupal\Core\File\FileSystemInterface;
use Drupal\qwizard\Entity\QwStudentResults;
use Drupal\qwrest\QwRestGeneral;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QWCache.
 */
class QWCache
{
  // Directory of JSON folder
  public string $json_path = 'public://json_cache/';
  // getTotalQuizzes options to not include in JSON filename
  public array $banlist_for_cache_key_options = ['cache', 'force_flat_tags', 'types', 'ignore_access_check'];
  //public array $cache_tags = ['node_list:qw_simple_choice'];
  public array $cache_tags = [];

  public function __construct(QwizardGeneral $QwizardGeneral, QwRestGeneral $rest_service, FileSystemInterface $filesystem){
    $this->QwizardGeneral = $QwizardGeneral;
    $this->filesystem = $filesystem;
    $this->rest_service = $rest_service;
  }

  public function create(ContainerInterface $container)
  {
    return new static(
      $container->get('qwizard.membership'),
      $container->get('qwrest.general'),
      $container->get('filesystem')
    );
  }

  /**
   * Adds queue items to regenerate all JSON files
   *
   * @return void
   */
  public function generateAllQwCacheQueue(){
    $options = [
      'quizResultsCache' => 1,
      'getTotalQuizzes' => 1,
      'getOthersProgress' => 1,
    ];
    $this->generateQwCacheQueue($options);
  }

  /**
   * Given an options array, will create Queue tasks to re-generate JSON files
   *
   * @param $options
   * @return void
   */
  public function generateQwCacheQueue($options)
  {
    $default_options = [
      'quizResultsCache' => 0,
      'getTotalQuizzes' => 0,
      'getOthersProgress' => 0,
    ];
    $options = array_merge($default_options, $options);

    $QwizardGeneral = $this->QwizardGeneral;
    $statics = $QwizardGeneral->getStatics();
    // format example: [200 => [185], 201 => [188], 202 => [191]]
    $test_courses = $statics['test_classes'];
    $study_courses = $statics['study_classes'];

    // quizResultsCache_
    if (!empty($options['quizResultsCache'])) {
      $this->_rebuild_quizResultsCache($test_courses);
      $this->_rebuild_quizResultsCache($study_courses);
    }

    // getTotalQuizzes_
    if (!empty($options['getTotalQuizzes'])) {
      $cache_files = $this->filesystem->scanDirectory('public://json_cache', '/(getTotalQuizzes_)/');
      foreach ($cache_files as $cache_file) {
        $cache_options = json_decode(str_replace(['getTotalQuizzes_', '.json'], '', $cache_file->filename), true);
        $this->_rebuild_getTotalQuizzes_Cache($cache_options);
      }
    }

    // getOthersProgress
    if (!empty($options['getOthersProgress'])) {
      foreach ($test_courses as $course_id=>$classes) {
        $this->_rebuild_getOthersProgressCache($course_id);
      }
    }
  }

  /**
   * Used to strip away options from getTotalQuizzes in order to limit file characters, due to the 255 limit
   *
   * @param $options
   * @return array
   */
  public function get_options_for_cache_key($options): array
  {
    foreach ($this->banlist_for_cache_key_options as $key) {
      if (isset($options[$key])) unset($options[$key]);
    }

    // If the option given is just the default option, don't bother including it in the key
    $default_options = $this->QwizardGeneral->getTotalQuizzesDefaultOptions();
    foreach($options as $key=>$value){
      if(array_key_exists($key, $default_options) && $value == $default_options[$key]){
        unset($options[$key]);
      }
    }

    return $options;
  }

  /**
   * Given a cache key, returns a JSON URI
   *
   * @param $key
   * @return string
   */
  private function getCacheFileURI($key): string
  {
    // Make sure the directory works, as long as the URI is being called.
    $this->filesystem->prepareDirectory($this->json_path, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    return $this->json_path . $key . '.json';
  }

  public function checkCache($cache_key, $ignore_json = false)
  {
    $QwizardGeneral = $this->QwizardGeneral;
    $statics = $QwizardGeneral->getStatics();
    $cached_data = null;

    // Load from Drupal cache first if possible, is faster than file cache if memcache is hit
    $cache = \Drupal::cache()->get($cache_key);
    if (!empty($cache) && !empty($cache->data)) {
      $cached_data = $cache->data;
    }
    else {
      if (!empty($statics['enable_json_cache']) && !$ignore_json) {
        $cache = $this->getCacheFile($cache_key);
      }

      if (!empty($cache)) {
        $cached_data = $cache;
        \Drupal::cache()->set($cache_key, $cached_data, strtotime('+1 day'), $this->cache_tags);
      } else {
        // @todo could try to build objects here, but will hit 512 MEM limits
        //Throw new \Exception('cache not found for '.$cache_key);
        //\Drupal::logger('qwcache')->error('cache not found for ' . $cache_key);
      }
    }

    return $cached_data;
  }

  /**
   * Loads a JSON cache file by key
   * @param $key
   * @return array|null
   */
  public function getCacheFile($key): ?array
  {
    $filename = $this->getCacheFileURI($key);
    $data = null;

    if (file_exists($filename)) {
      $data = file_get_contents($filename);
      $data = json_decode($data, true);
    }

    return $data;
  }


  /**
   * Sets a JSON cache file by key, given data
   * @param $key
   * @param $data
   * @return void
   */
  public function setCacheFile($key, $data, $ignore_json = false)
  {
    // Don't bother caching if we got an empty result set
    if(empty($data) || empty($key)){
      return;
    }

    // Set drupal cache as a backup here
    $expiration = strtotime('+1 day');
    \Drupal::cache()->set($key, $data, $expiration, $this->cache_tags);

    if(!$ignore_json) {
      if (!is_string($data)) {
        $data = json_encode($data);
      }

      $filename = $this->getCacheFileURI($key);
      $fileRepository = \Drupal::service('file.repository');
      $fileRepository->writeData($data, $filename, FileSystemInterface::EXISTS_REPLACE);
    }
  }

  /**
   * Rebuilds cache file, given a course_id and class_id. Used by queue worker.
   *
   * @param $course_id
   * @param $class_id
   * @return array
   */
  public function buildClassCache($course_id, $class_id)
  {
    $data = $this->getQuizByTopicData($course_id, $class_id);
    $key = 'quizResultsCache_' . $course_id . '_' . $class_id;
    $this->setCacheFile($key, $data);

    return $data;
  }

  /**
   * Given a course_id, rebuilds getOthersProgress cache file. Used by queue worker.
   * @param $course_id
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildOthersProgressCache($course_id)
  {
    $rest_service = $this->rest_service;
    $course_results = $rest_service->getSiteResultsData($course_id);
    // Go down two levels in array, first item is fine so just using reset
    $v = reset($course_results);
    $course_results = reset($v)['month'];

    // Convert scores to twig friendly percents, and divide by 2 since the twig template needs it
    foreach ($course_results as $key => $value) {
      if (!is_array($value) && str_starts_with($key, 'score_')) {
        $course_results[$key] = round($value * 100);
      }
    }
    foreach ($course_results['test_mode'] as $key => $value) {
      if (str_starts_with($key, 'score_')) {
        $course_results['test_mode'][$key] = round(($value / 2) * 100);
      }
    }
    $key = 'getOthersProgress_' . $course_id;
    $this->setCacheFile($key, $course_results);
  }

  /**
   * Given a set of options, will rebuild getTotalQuizzes cache file. Used by queue worker.
   * @param $options
   * @return void
   */
  public function buildGetTotalQuizzesCache($options)
  {
    $QwizardGeneral = $this->QwizardGeneral;
    $options['cache'] = 0;
    $data = $QwizardGeneral->getTotalQuizzes($options);
    $options_for_cache_key = $this->get_options_for_cache_key($options);
    $cache_key = 'getTotalQuizzes_' . json_encode($options_for_cache_key);
    $this->setCacheFile($cache_key, $data);
  }

  private function getQuizByTopicData($course_id, $class_id)
  {
    $QwizardGeneral = $this->QwizardGeneral;
    $statics = $QwizardGeneral->getStatics();
    $multiquiz_quizzes = $statics['multiquiz_quizzes'];
    $banlist_topics = $statics['banlist_topics'];
    $qwiz_id = $multiquiz_quizzes[$course_id];
    $topic_results = [];
    $questions_with_topics = [];

    $quiz_cache_key = 'quizResultsCache_' . $course_id . '_' . $class_id;

    $params = ['course_id' => $course_id, 'class' => $class_id];
    $all_question_nids = $QwizardGeneral->getTotalQuizzes($params);
    $question_storage = \Drupal::entityTypeManager()->getStorage('node');
    $all_question_nodes = $question_storage->loadMultiple($all_question_nids);

    // Load all topic tags
    $tag_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $all_topics_loaded = $tag_storage->loadTree('topics', 0, null, 1);
    $all_topics = [];
    foreach($all_topics_loaded as $topic){
      $all_topics[$topic->id()] = $topic;
    }

    $topic_names = $QwizardGeneral->getTopicsInQwiz($qwiz_id);
    $topic_names[224] = 'Random';

    // Used for college classes, they need their totals generated from the main topics
    /*$class_type = $QwizardGeneral->getClassType($class_id, $course_id);
    if($class_type == 'college_study'){
      $class_to_use = $QwizardGeneral->getStatics('test_classes')[$course_id];
      $college_params = [];
      $college_params['class'] = $class_to_use;
      $college_params['question_ids'] = $QwizardGeneral->getTotalQuizzes(['course_id' => $course_id, 'class' => $class_id]);
    }*/

    foreach ($topic_names as $topic_id => $topic_name) {
      $topic = $all_topics[$topic_id];

      // Ignore unpublished topic tags
      if(!$topic->isPublished()){
        continue;
      }

      $params = [
        'course_id' => $course_id,
        'class' => $class_id,
        'topics' => [$topic_id, $multiquiz_quizzes[$course_id]]
      ];

     /* if($class_type == 'college_study'){
        $params = array_merge($params, $college_params);
      }*/

      $topic_results[$topic_id] = [
        'label' => $topic_name,
        'name' => $topic_name,
        'total_questions' => count($QwizardGeneral->getTotalQuizzes($params)),
        'seen' => 0,
        'attempted' => 0,
        'correct' => 0,
      ];
    }

    // Get ID's of all paragraphs attached to nodes to load them all at once
    $paragraphs_by_id = [];
    if ($statics['enable_paragraph_quiz_tagging']) {
      $paragraph_target_ids_to_load = [];
      // slowQuery @ about 6 seconds. This is cached normally.
      // Optimizing uncached would require finding an alternative to getValue() looping
      foreach ($all_question_nodes as $question) {
        //$loop = $question->get('field_specified_topics')->getValue(); //6+ seconds
        $loop = $question->field_specified_topics; // 2.9 seconds
        //$loop = $question->get('field_specified_topics'); // 3.2 seconds
        foreach ($loop as $paragraph_target_id) {
          //$target_id = $paragraph_target_id['target_id'];
          $target_id = $paragraph_target_id->target_id;
          $paragraph_target_ids_to_load[$target_id] = $target_id;
        }
      }

      $paragraphs_by_id = \Drupal\paragraphs\Entity\Paragraph::loadMultiple($paragraph_target_ids_to_load);
    }

    // Create array of question topics that we need
    foreach ($all_question_nodes as $question) {
      if ($statics['enable_paragraph_quiz_tagging']) {
        $specified_topics = $question->get('field_specified_topics')->getValue();
        foreach ($specified_topics as $specified_topic_id) {
          $specified_topic_id = $specified_topic_id['target_id'];
          if (!empty($paragraphs_by_id[$specified_topic_id])) {
            $specified_paragraph = $paragraphs_by_id[$specified_topic_id];
            $specified_course = $paragraphs_by_id[$specified_topic_id]->get('field_course')->getValue();
            if (!empty($specified_course[0]['target_id']) &&
              $specified_course[0]['target_id'] == $course_id &&
              !empty($specified_paragraph->get('field_topic')->getValue())) {
              $topic_id = $specified_paragraph->get('field_topic')->getValue()[0]['target_id'];

              //if (empty($topic_names[$topic_id]) || in_array($topic_id, $banlist_topics) || !$all_topics[$topic_id]->isPublished()) {
              if (empty($topic_names[$topic_id]) || !$all_topics[$topic_id]->isPublished()) {
                continue;
              }

              $questions_with_topics[$question->id()][$topic_id] = $topic_id;
            }
          }
        }
      /*  if($class_id == 461 && $question->id() == 10243){
          var_dump('cache test');
          var_dump($questions_with_topics[$question->id()]); exit;
        }*/
      } else {
        //Used for old method of getting topics, refactored in favor of paragraphs
        if (!empty($question->field_topics)) {
          $questions_with_topics[$question->id()] = [];
          foreach ($question->field_topics as $topic_data) {
            $topic_id = $topic_data->target_id;
            // If QwizTopic does not belong to this class or is in banlist, give them a free point
            if (empty($topic_names[$topic_id]) || in_array($topic_id, $banlist_topics)) {
              continue;
            }

            $questions_with_topics[$question->id()][$topic_id] = $topic_id;
          }
        }
      }
    }
    $cache_data = [];
    $cache_data['topic_results'] = $topic_results;
    $cache_data['questions_with_topics'] = $questions_with_topics;
    \Drupal::cache()->set($quiz_cache_key, $cache_data, strtotime('+1 day'), $this->cache_tags);

    return $cache_data;
  }

  private function _rebuild_getOthersProgressCache($course_id)
  {
    $queue_name = 'qw_cache_getOthersResults_queue_worker';
    $queue = \Drupal::queue($queue_name);
    $queue->createQueue();

    $queue_data = [
      'course_id' => $course_id,
    ];

    $result = $queue->createItem($queue_data);
  }

  private function _rebuild_getTotalQuizzes_Cache($options)
  {
    $queue_name = 'qw_cache_getTotalQuizzes_queue_worker';
    $queue = \Drupal::queue($queue_name);
    $queue->createQueue();

    $options['cache'] = 0;

    $queue_data = [
      'type' => 'getTotalQuizzes',
      'options' => $options,
    ];

    $result = $queue->createItem($queue_data);
  }

  private function _rebuild_quizResultsCache($courses)
  {
    foreach ($courses as $course_id => $test_classes) {
      foreach ($test_classes as $class_id) {
        $queue_name = 'qw_cache_quizResultsCache_queue_worker';
        $queue = \Drupal::queue($queue_name);
        $queue->createQueue();

        $queue_data = [
          'type' => 'quizResultsCache',
          'class_id' => $class_id,
          'course_id' => $course_id,
        ];

        $result = $queue->createItem($queue_data);
      }
    }
  }


}
