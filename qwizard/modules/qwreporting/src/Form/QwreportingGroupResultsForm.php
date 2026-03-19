<?php

namespace Drupal\qwreporting\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\qwreporting\GroupsInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a Quiz Wizard Reporting form.
 */
class QwreportingGroupResultsForm extends FormBase {

  private $groups;

  /**
   * Construct GroupEditForm.
   *
   * @param \Drupal\qwreporting\GroupsInterface @groups
   *   Object to inject.
   */
  public function __construct(GroupsInterface $groups) {
    $this->groups = $groups;
  }

  /**
   * Getting Group object from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Inject Container.
   *
   * @return \Drupal\qwreporting\Form\GroupEditForm|mixed|object|null
   *   self with injected object.
   */
  public static function create(ContainerInterface $container) {
    $groups = $container->get('qwreporting.groups');
    return new static($groups);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwreporting_group_results';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    // Sometimes AJAX is weird about $this->groups being unavailable
    if(empty($this->groups)){
      $this->groups = \Drupal::service('qwreporting.groups');
    }

    if(!empty($_GET['op']) && $_GET['op'] == 'Clear') {
      $url = Url::fromRoute('qwreporting.results.individual', ['group' => $group]);
      return new RedirectResponse($url->toString());
    }

    $group_data = $this->getGroupResultData($group, $form_state);

    $form = [];

    // Date Filter
    $form['form_filters'] = [
      '#type' => 'fieldset',
      '#title' => null,
      '#prefix' => '<div id="form-fieldset-wrapper">',
      '#suffix' => '</div>',
    ];
    $form['#action'] = Url::fromRoute('qwreporting.results.individual', ['group' => $group])
      ->toString();

    $form_state->setMethod('GET');
    $form['#method'] = 'GET';
    $data = NULL;
    $courses = $this->groups->getCourses();
    $group_admins = [];

    // Date Filter
    $form['form_filters']['date_filters'] = [
      '#type' => 'fieldset',
      '#title' => 'Date Filters',
      '#prefix' => '<div class="form-fieldset-wrapper date_filters">',
      '#suffix' => '</div>',
    ];

    $date_start = $group_data['#filter']['days']['start'];
    $period_default = $date_start;
    $date_end = $group_data['#filter']['days']['end'];
    $period_filter = $group_data['#filter']['date_filter_option'];
    if($group_data['#filter']['date_filter_option'] == 'by_period'){
      $date_start = null;
      $date_end = null;
    }elseif($group_data['#filter']['date_filter_option'] == 'by_dates'){
      $period_default = null;
    }
    if(empty($date_start)) $date_start = date('Y-m-d', 1641059861);// 1-1-2022
    if(empty($date_end)) $date_end = date('Y-m-d', strtotime('now'));// 1-1-2022

    $form['form_filters']['date_filters']['filter_option'] = [
      '#type' => 'radios',
      '#default_value' => !empty($period_filter) ? $period_filter : 'by_period',
      '#options' => ['by_period' => t('By Period'), 'by_dates' => t('By Date Range')],
      '#required' => true,
    ];

    $form['form_filters']['date_filters']['period'] = [
      '#type' => 'select',
      '#title' => 'Period: ',
      '#default_value' => $group_data['#filter']['days']['start'],
      '#options' => $group_data['#filter']['days_options'],
    ];
    $form['form_filters']['date_filters']['by_day'] = [
      '#type' => 'fieldset',
      '#title' => '',
      '#prefix' => '<div id="date-filter-fieldset-wrapper" style="float:left;">',
      '#suffix' => '</div>',
    ];
    $form['form_filters']['date_filters']['by_day']['date_start'] = [
      '#type' => 'date',
      '#title' => 'Start: ',
      #'#default_value' => date('Y-m-d', strtotime($group_data['#filter']['days']['start']))
      '#default_value' => $date_start
    ];
    $form['form_filters']['date_filters']['by_day']['date_end'] = [
      '#type' => 'date',
      '#title' => 'End: ',
      '#default_value' => $date_end,
    ];

    $group_data['#filter']['topics'] = $this->isolateTopicFilterToCurrentTopic($group_data['#filter']['topics'], $group_data['#selected_topic']);

    // Topic Select
    $topics = [];
    $form['form_filters']['topic'] = [
      '#type' => 'select',
      '#title' => 'Topic ',
      '#options' => $group_data['#filter']['topics'],
      '#default_value' => $group_data['#selected_topic'],
    ];

    $first_topic = reset($group_data['#filter']['topics']);
    if(empty($group_data['#filter']['topics']) || empty($_GET['topic']) || count($group_data['#filter']['topics']) == 1 && count($first_topic) == 1){
      $form['form_filters']['topic']['#access'] = false;
      if(!empty($_GET['topic'])){
        $form['form_filters']['topic'] = [
          '#type' => 'hidden',
          '#title' => 'Topic: ',
          '#default_value' => $_GET['topic'],
          '#value' => $_GET['topic'],
        ];
      }
    }
    $form['form_filters']['clearfix'] = [
      '#type' => 'markup',
      '#markup' => '<div class="clear clearfix" style="clear:both;"></div>',
    ];



    $form['form_filters']['actions'] = [
      '#type' => 'actions',
      '#weight' => 78,
    ];

    $form['form_filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Filter'),
    ];
    $form['form_filters']['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => t('Clear'),
      '#button_type' => 'secondary',
    ];

    $form['group_results_header'] = [
      '#type' => 'markup',
      '#markup' => $group_data['rendered_header'],
      '#weight' => -80,
    ];
    $form['group_results'] = [
      '#type' => 'markup',
      '#markup' => $group_data['rendered'],
      '#weight' => 80,
    ];


    #dpm($group_data['rendered_header']);

    $form['#attached']['library'][] = 'chosen/drupal.chosen.all';
    $form['#attached']['library'][] = 'conditional_fields/conditional_fields';
    //$form['#attached']['library'][] = 'qwreporting/qwreporting.jscrollpane';
    $form['#attached']['library'][] = 'qwreporting/qwreporting.double_scroll';
    $form['#attached']['library'][] = 'qwreporting/qwreporting.individual_results_drag_scrolling';
    $form['#attached']['library'][] = 'qwreporting/horizontal_scroller';

    $form['#after_build'][] = 'conditional_fields_form_after_build';
    /*$form['#attached']['drupalSettings']['conditionalFields'] = [
      'effects' => ['#edit-student-profile-profiles-0-entity-field-my-pronouns-wrapper' => []],
    ];*/
    $form['form_filters']['date_filters']['by_day']['#states'] = [
      'visible' => [
        ':input[name="filter_option"]' => [
          'value' => 'by_dates',
        ],
      ],
    ];
    $form['form_filters']['date_filters']['period']['#states'] = [
      'visible' => [
        ':input[name="filter_option"]' => [
          'value' => 'by_period',
        ],
      ],
    ];


    $form['form_filters']['tabs'] = [
      '#type' => 'markup',
      '#markup' => $this->getTabs($group_data['#selected_topic'], $group_data['#group']['main_categories'], $group, $group_data['#course']),
      '#weight' => '-50',
    ];

    $query_params = \Drupal::request()->query->all();
    $download_link_route = !empty($query_params) ? 'qwreporting.download_all' : 'qwreporting.excel';
    $form['form_filters']['download_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Download This Table'),
      '#url' => Url::fromRoute($download_link_route, [
        'group' => $group,
      ],
      [
        'attributes' => [
          'class' => ['excel-link', 'mr-2'],
          'target' => '_blank',
        ],
        'query' => $query_params,
      ]),
      '#weight' => 500,
    ];

    if (empty($query_params)) {
      $form['form_filters']['download_all'] = [
        '#type' => 'link',
        '#title' => $this->t('Download All Topic Results'),
        '#url' => Url::fromRoute('qwreporting.download_all', [
          'group' => $group,
        ],
        [
          'attributes' => [
            'class' => ['excel-link', 'd-inline-block', 'mx-2'],
            'target' => '_blank',
          ],
        ]),
        '#weight' => 501,
      ];
    }

    if (!empty($group_data['#selected_class'])) {
      $selected_class_id = $group_data['#selected_class'];
      $selected_class = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($selected_class_id);
      $is_readiness = (bool) $selected_class->get('field_is_readiness')->getString();
      if ($is_readiness) {
        // Show a link to export report of time spent per test.
        $form['form_filters']['time_spent_per_test'] = [
          '#type' => 'link',
          '#title' => $this->t('Time Spent per Test'),
          '#url' => Url::fromRoute('qwreporting.download_snapshots', [
            'group' => $group,
            'exam_id' => $selected_class_id,
          ],
          [
            'attributes' => [
              'class' => ['excel-link', 'd-inline-block', 'mx-2'],
              'target' => '_blank',
            ],
          ]),
          '#weight' => 502,
        ];
      }
    }
    #selected_class

    return $form;
  }

  function getReadinessQwizStrings(){
    return [200 => ['200_463_224', '200_464_224'], 201 => [], 202 => ['202_582_583', '202_593_602']];
  }

  function isolateTopicFilterToCurrentTopic($topics, $selected_topic_string){
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $statics = $QwizardGeneral->getStatics();
    $multiquiz_quizzes = $statics['multiquiz_quizzes'];
    $banlist_topics = $statics['banlist_topics'];
    $study_test_classes = $statics['study_test_classes'];
    $study_classes = $statics['study_classes'];
    $test_classes = $statics['test_class_ids'];


    $readiness_classes = $this->getReadinessQwizStrings();



    $only_primary_topics = false;
    foreach($study_test_classes as $study_test_class){
      if(str_contains($selected_topic_string, '_'.$study_test_class.'_')){
        $only_primary_topics = true;

        //ZUKU-1314 we don't want the topic filter anymore on study/test
        return [];
      }
    }

    if(empty($selected_topic_string)){
      // isolate to main topic results
      $only_primary_topics = true;
    }elseif(str_contains($selected_topic_string, '__')){
      $only_primary_topics = true;
    }
    elseif(!$only_primary_topics){
      $selected_info = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($selected_topic_string);
      if(!empty($selected_info)) {
        $selected_class_id = $selected_info['class'];
        $selected_course_id = $selected_info['course'];
        $selected_topic_id = $selected_info['topic'];
        $selected_qwiz_id = $selected_info['qwiz'];
        $is_primary_course = $selected_info['is_primary_course'];

        // No more tab filters for study/test, ZUKU-1314
        if(in_array($selected_class_id, $study_test_classes)){
          return [];
        }

        foreach($topics as $topic_string=>$topics_in_class){
          if(!is_array($topics_in_class)){
            unset($topics[$topic_string]); continue;
          }
          $first_topic_string = array_key_first($topics_in_class);

          // Keep readiness topics if readiness is selected
          if(in_array($selected_topic_string, $readiness_classes[$selected_course_id])){
            if(in_array($first_topic_string, $readiness_classes[$selected_course_id])){

              continue;
            }
          }

          if(!str_contains($first_topic_string, '_'.$selected_class_id.'_')){
            unset($topics[$topic_string]); continue;
          }
        }
      }
    }

    if($only_primary_topics){
      foreach($topics as $topic_string=>$topics_in_class){
        if(empty($topic_string)){
          continue;
        }

        $first_topic_string = array_key_first($topics_in_class);
        if(!str_contains($first_topic_string, '__')){
          unset($topics[$topic_string]);
          continue;
        }
      }
    }

    #dpm($topics);
    return $topics;
  }

  public function getTabs($selected_topic_string, $classes, $group_id, $course_id){
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $student_service = \Drupal::service('qwreporting.students');
    $statics = $QwizardGeneral->getStatics();
    $study_test_classes = $statics['study_test_classes'];
    #dpm($classes);

    if(empty($selected_topic_string)){

    }else{

      $selected_info = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($selected_topic_string);
      if(!empty($selected_info)) {
        $selected_class_id = $selected_info['class'];
        $selected_topic_id = $selected_info['topic'];
        $selected_qwiz_id = $selected_info['qwiz'];
        $is_primary_course = $selected_info['is_primary_course'];
      }
    }

    $tabs_html = '<nav class="tabs" role="navigation" aria-label="Tabs"><ul class="nav nav-tabs primary">';
    $active_class = '';
    if(empty($_GET['topic'])) {
      $active_class = 'active';
    }
    $link_url = Url::fromRoute('qwreporting.results.individual', ['group' => $group_id])->toString();
    $tabs_html .= '<li class="nav-item '.$active_class.'"><a href="'.$link_url.'" class="nav-link '.$active_class.'">Overview</a></li>';
    $i = 0;
    foreach($classes as $class_id=>$class_info) {
      $i++;
      $topics = $class_info['topics'];
      $class_name = $class_info['label'];
      $class_name = str_replace('Timed Tests By Practice Domain', 'Timed Quizzes by Practice Domain', $class_name);
      $class_name = str_replace('Ohio State Custom Readiness - 1', 'Ohio State Custom Readiness', $class_name);

      $students = $student_service->getStudents($group_id, NULL, NULL, NULL, $class_id, NULL, TRUE);

      $first_topic_string = $course_id . '_' . $class_id . '_';

      $selected_info = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($first_topic_string);
      if (is_string($topics) || !$this->studentHasResults($students, $selected_info)) {
        continue;
      }

      $active_class = '';

      if(in_array($class_id, $study_test_classes)) {
        //$first_topic_string = str_replace('_' . $class_id . '_', '__', $first_topic_string);

        //ZUKU-1314
        $first_topic_string = $course_id.'_'.$class_id.'_';
      }

      if(empty($_GET['topic']) && $i == 1) {
        //$active_class = 'active';

      }elseif(!empty($_GET['topic'])){
        if(str_contains($_GET['topic'], '__') && $i == 1){
          $active_class = 'active';
        }
        elseif (str_contains($_GET['topic'], '_' . $class_id . '_')) {
          $active_class = 'active';
        }
        elseif(in_array($_GET['topic'], $this->getReadinessQwizStrings()[$course_id]) &&
          in_array($first_topic_string, $this->getReadinessQwizStrings()[$course_id])){
          $active_class = 'active';
        }
      }


/*      if($i == 1){
        $first_topic_string = '';
      }*/
      $link_url = Url::fromRoute('qwreporting.results.individual', ['group' => $group_id])->toString().'?topic='.$first_topic_string;
      //$link_url = Url::fromRoute('qwreporting.results.individual', ['group' => $group_id])->toString().'?topic='.$course_id.'_'.$class_id.'_'.$first_topic_id;

      $class_name = trim(str_replace('Test Mode', 'Timed Tests', $class_name));
      $class_name = trim(str_replace(['VTNE', 'BCSE', 'NAVLE'], '', $class_name));
      // Steve wants this class gone from overview
      if($class_name == 'Calcs, Image and Vocab Qs'){
        continue;
      }


      $tabs_html .= '<li class="nav-item '.$active_class.'"><a href="'.$link_url.'" class="nav-link '.$active_class.'">'.$class_name.'</a></li>';
    }
    $tabs_html .= '</ul></div>';

    return $tabs_html;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (mb_strlen($form_state->getValue('name')) < 5) {
      $form_state->setErrorByName('name', $this->t('Message should be at least 5 characters.'));
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Just let it submit to itself with GET
  }

  public function getGroupResultData($group, $form_state){
    \Drupal::service('page_cache_kill_switch')->trigger();
    $groups = $this->groups->getGroups();
    if(empty($groups[$group])){
      \Drupal::messenger()->addWarning('You are not assigned to this reporting group.');
      throw new AccessDeniedHttpException();
    }

    $query = \Drupal::request()->query->all();
    $student_service = \Drupal::service('qwreporting.students');
    $group_service = \Drupal::service('qwreporting.groups');
    $renderer = \Drupal::service('renderer');
    $selected_topic_string = isset($query['topic']) ? $query['topic'] : null;
    $selected_qwiz_id = null;
    $selected_topic_id = null;
    $selected_class_id = null;
    $selected_course_id = null;
    $is_primary_course = true;
    $show_all_topics_in_table = false;
    if(!empty($selected_topic_string)){
      $selected_info = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($selected_topic_string);
      if(!empty($selected_info)) {
        $selected_class_id = $selected_info['class'];
        $selected_course_id = $selected_info['course'];
        $selected_topic_id = $selected_info['topic'];
        $selected_qwiz_id = $selected_info['qwiz'];
        $is_primary_course = $selected_info['is_primary_course'];
      }
      $show_all_topics_in_table = $is_primary_course;
    }

    $period = isset($query['period']) ? $query['period'] : null;
    $period_days_start = isset($query['date_start']) ? $query['date_start'] : null;
    $period_end = isset($query['date_end']) ? $query['date_end'] : null;
    $date_filter_option = isset($query['filter_option']) ? $query['filter_option'] : null;

    if($date_filter_option == 'by_period'){
      $period_days_start = null;
      $period_end = null;
    }elseif($date_filter_option == 'by_dates'){
      $period = $period_days_start;
    }

    $group_details = $group_service->getGroupData($group);

    $course = $group_details['course'];

    $current_path = explode('?', \Drupal::request()->getRequestUri())[0];

    $since_timestamp = null;
    if(!empty($period)){
      $since_timestamp =  strtotime($period);
      $since_date_string = date('Y-m-d', $since_timestamp);
    }else{
      $since_date_string = date('Y-m-d', 1641059861);// 1-1-2022
    }
    $since_end_timestamp = null;
    if(!empty($period_end)){
      $since_end_timestamp =  strtotime($period_end);
      $since_end_date_string = date('Y-m-d', $since_end_timestamp);
      $since_end_timestamp = $since_end_timestamp + 86400;// add a day to get end of day
    }else{
      $since_end_date_string = date('Y-m-d', strtotime('+1 day'));
    }
    $students = $student_service->getStudents($group, $selected_topic_id, $since_timestamp, $since_end_timestamp, $selected_class_id, $selected_qwiz_id, TRUE);
    #dpm($students);
    #dpm($selected_topic_id);
    if(empty($students)){
      \Drupal::messenger()->addWarning('Was unable to load student information for this group. Set students at /admin/qwreporting/'.$group.'/edit');
    }

    $days_options = [
      null => 'Select a date range',
      'Days' => [
        date('Y-m-d',strtotime("-1 days")) => 'Last 1 day',
        date('Y-m-d',strtotime("-3 days")) => 'Last 3 days',
        date('Y-m-d',strtotime("-5 days")) => 'Last 5 days',
        date('Y-m-d',strtotime("-10 days")) => 'Last 10 days'
      ],
      'Weeks' => [
        date('Y-m-d',strtotime("-1 weeks")) => 'Last week',
        date('Y-m-d',strtotime("-2 weeks")) => 'Last 2 weeks',
        date('Y-m-d',strtotime("-3 weeks")) => 'Last 3 weeks'
      ],
      'Months' => [
        date('Y-m-d',strtotime("-1 months")) => 'Last month',
        date('Y-m-d',strtotime("-2 months")) => 'Last 2 months',
        date('Y-m-d',strtotime("-4 months")) => 'Last 4 months',
        date('Y-m-d',strtotime("-6 months")) => 'Last 6 months',
        date('Y-m-d',strtotime("-8 months")) => 'Last 8 months' ,
        date('Y-m-d',strtotime("-10 months")) => 'Last 10 months',
        date('Y-m-d',strtotime("-1 year")) => 'Last 1 year',
        date('Y-m-d',strtotime("-2 years")) =>  'Last 2 years',
      ],
    ];

    $days = ['start' => $since_date_string, 'end' => $since_end_date_string];
    $group_details['topics'] = $this->formatTopicOptGroups($group_details['topics'], $selected_course_id);
    //$group_details['topics'] = array_merge(['' => 'Study/Test Mode Results'], $group_details['topics']);
    $filter = [
      'topics'         => $group_details['topics'],
      'current_path'   => $current_path,
      'selected_topic' => $selected_topic_string,
      'period'         => $period,
      'days'           => $days,
      'days_options'   => $days_options,
      'date_filter_option' => $date_filter_option,
    ];
    #dpm($students);


    // Get topics list
    $topics = [];
    if($show_all_topics_in_table) {
      $QwCache = \Drupal::service('qwizard.cache');
      $quiz_cache_key = 'quizResultsCache_' . $selected_course_id . '_' . $selected_class_id;
      $cache = $QwCache->checkCache($quiz_cache_key);
      if (empty($cache['topic_results']) || empty ($cache['questions_with_topics'])) {
        $cache = $QwCache->buildClassCache($selected_course_id, $selected_class_id);
      }
      $topics = $cache['topic_results'];

      // no random
      if(!empty($topics[224])){
        unset($topics[224]);
      }
    }

    $test_mode_classes = \Drupal::service('qwizard.general')->getStatics('test_mode_classes');

    $group_results = [
      '#theme' => 'qwreporting_individual_results',
      '#students' => $students,
      '#group' => $group_details,
      '#course' => $course,
      '#selected_topic' => $selected_topic_string,
      '#filter' => $filter,
      '#is_primary_course' => $is_primary_course,
      '#selected_class' => $selected_class_id,
      '#selected_topic_id' => $selected_topic_id,
      '#selected_qwiz_id' => $selected_qwiz_id,
      '#query_params' => $_GET,
      '#topics' => $topics,
      '#show_all_topics_in_table' => $show_all_topics_in_table,
      '#is_timed_test_mode' => in_array($selected_class_id, $test_mode_classes),
    ];

    $group_results['rendered'] = $renderer->render($group_results);

    $header_render = [
      '#theme' => 'qwreporting_individual_results_header',
      '#group' => $group_results['#group'],
      '#selected_topic' => $group_results['#selected_topic'],
      '#filter' => $group_results['#filter'],
    ];
    $group_results['rendered_header'] = $renderer->renderPlain($header_render);

    #dpm($group_results);
    return $group_results;
  }

  function formatTopicOptGroups($options, $course_id){
    $formatted_options = [];
    $options_by_class = [];
    $classes_to_load = [];

    $formatted_options['Ohio State Custom Readiness - 1']['201_675_'] = 'Total';

    foreach($options as $selected_topic_string=>$long_label) {
      $selected_info = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($selected_topic_string);

      if (!empty($selected_info)) {
        $selected_class_id = $selected_info['class'];
        $classes_to_load[$selected_class_id] = $selected_class_id;
        $selected_topic_id = $selected_info['topic'];
        $selected_qwiz_id = $selected_info['qwiz'];
        $is_primary_course = $selected_info['is_primary_course'];
        $options_by_class[$selected_class_id][$selected_topic_string] = $long_label;
      }
    }
    $classes_by_id = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($classes_to_load);


    foreach($options_by_class as $class_id=>$class_options){
      $part_to_get = 1;

      // The dash in ohio breaks this
      if(in_array($class_id, [\Drupal::service('qwizard.general')->getStatics('ohio_class_id')])){
        $part_to_get = 2;
        $is_primary_course = true;
      }

      $class_label = $classes_by_id[$class_id]->label();
      $formatted_options[$class_label] = [];

      if($is_primary_course) {
        $formatted_options[$class_label] = [
          $course_id . '_' . $class_id . '_' => 'Total'
        ];
      }

      foreach($class_options as $selected_topic_string=>$long_label){
        if(str_contains($long_label, ' - ')){
          $long_label = explode(' - ', $long_label)[$part_to_get];
        }
        $formatted_options[$class_label][$selected_topic_string] = $long_label;
      }
    }

    // Now sort by name within each class
    foreach($formatted_options as $class_id => $class_options){
      uasort($class_options, 'QwReportingGroupResultsFormOptionSort');
      $formatted_options[$class_id] = $class_options;
    }

    #dpm($options);
    #dpm($formatted_options);
    return $formatted_options;
  }

  /**
   * Checks if students has results.
   */
  public function studentHasResults($students, $selected_info) {
    $has_result = FALSE;
    $is_primary_course = $selected_info['is_primary_course'];
    $show_all_topics_in_table = $is_primary_course;

    $class_id = $selected_info['class'];

    if ($show_all_topics_in_table) {
      foreach ($students as $student) {
        $data = $student['data'];
        if (!empty($data['attempted']) && !empty($data['totalScore']) && !empty($data['avg'])) {
          $has_result = TRUE;
          break;
        }
      }
    }
    else {
      if ($is_primary_course) {
        foreach ($students as $student) {
          $data = $student['data'];
          if (!empty($data['test_mode']['totalQuestion'])) {
            $has_result = TRUE;
            break;
          }
        }
      }
      else {
        foreach ($students as $student) {
          if (!empty($student['data']['secondary'][$class_id])) {
            if (isset($student['data']['secondary'][$class_id]['qwiz_id'])) {
              $secondary_class = $student['data']['secondary'][$class_id];
            }
            else {
              $secondary_class = reset($student['data']['secondary'][$class_id]);
            }
            if (isset($secondary_class['attempted']) && $secondary_class['attempted'] > 0) {
              $has_result = TRUE;
              break;
            }
          }
        }
      }
    }
    return $has_result;
  }

}
