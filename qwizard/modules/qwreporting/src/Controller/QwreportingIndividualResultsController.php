<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\qwreporting\GroupsInterface;
use Drupal\qwreporting\StudentsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Quiz Wizard Reporting routes.
 */
class QwreportingIndividualResultsController extends ControllerBase {

  /**
   * Group service.
   *
   * @var \Drupal\qwreporting\GroupsInterface
   */
  private $groups;

  /**
   * Student service.
   *
   * @var \Drupal\qwreporting\StudentsInterface
   */
  private $students;

  /**
   * QwreportingIndividualResultsController constructor.
   *
   * @param \Drupal\qwreporting\GroupsInterface $groups
   *   Groups object.
   * @param \Drupal\qwreporting\StudentsInterface $students
   *   Student object.
   */
  public function __construct(GroupsInterface $groups, StudentsInterface $students) {
    $this->groups = $groups;
    $this->students = $students;
  }

  /**
   * Create self with injected objects.
   */
  public static function create(ContainerInterface $container) {
    $group = $container->get('qwreporting.groups');
    $students = $container->get('qwreporting.students');
    return new static($group, $students);
  }

  /**
   * Builds the response.
   * Used for /admin/qwreporting/$tid/results/individual
   */
  public function build($group) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $groups = $this->groups->getGroups();
    if(empty($groups[$group])){
      \Drupal::messenger()->addWarning('You are not assigned to this reporting group.');
      throw new AccessDeniedHttpException();
    }

    $query = \Drupal::request()->query->all();
    $selected_class = null;
    $selected_topic_string = isset($query['topic']) ? $query['topic'] : null;
    $is_primary_course = true;
    $selected_topic_label = null;
    $selected_topic_id = null;
    $selected_class_id = null;
    if(!empty($selected_topic_string)){
      $selected_topic_string_parts = explode('_', $selected_topic_string);
      $selected_topic = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($selected_topic_string_parts[2]);
      $selected_topic_id = $selected_topic->id();
      if(!empty($selected_topic_string_parts[1])){
        $is_primary_course = false;
        $selected_class = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($selected_topic_string_parts[1]);
        $selected_class_id = $selected_class->id();
      }
    }
    $period = isset($query['period']) ? $query['period'] : null;
    $period_end = isset($query['period_end']) ? $query['period_end'] : null;

    $group_details = $this->groups->getGroupData($group);

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
    $students = $this->students->getStudents($group, $selected_topic_id, $since_timestamp, $since_end_timestamp);

    if(empty($students)){
      \Drupal::messenger()->addWarning('Was unable to load student information for this group. Set students at /admin/qwreporting/'.$group.'/edit');
    }

    $days = [
      'Days' => [
        'Last 1 day'    => date('Y-m-d',strtotime("-1 days")),
        'Last 3 days'    => date('Y-m-d',strtotime("-3 days")),
        'Last 5 days'    => date('Y-m-d',strtotime("-5 days")),
        'Last 10 days'   => date('Y-m-d',strtotime("-10 days"))
      ],
      'Weeks' => [
        'Last week'     => date('Y-m-d',strtotime("-1 weeks")),
        'Last 2 weeks'  => date('Y-m-d',strtotime("-2 weeks")),
        'Last 3 weeks'   => date('Y-m-d',strtotime("-3 weeks"))
      ],
      'Months' => [
        'Last month'    => date('Y-m-d',strtotime("-1 months")),
        'Last 2 months'  => date('Y-m-d',strtotime("-2 months")),
        'Last 4 months'  => date('Y-m-d',strtotime("-4 months")),
        'Last 6 months'  => date('Y-m-d',strtotime("-6 months")),
        'Last 8 months'  => date('Y-m-d',strtotime("-8 months")),
        'Last 10 months' => date('Y-m-d',strtotime("-10 months")),
        'Last 1 year' => date('Y-m-d',strtotime("-1 year")),
        'Last 2 years' => date('Y-m-d',strtotime("-2 years")),
      ],
   ];

    $days = ['start' => $since_date_string, 'end' => $since_end_date_string];
    $filter = [
      'topics'         => $group_details['topics'],
      'current_path'   => $current_path,
      'selected_topic' => $selected_topic_string,
      'period'         => $period,
      'days'           => $days
    ];
    #dpm($students);


    return [
      '#theme' => 'qwreporting_individual_results',
      '#students' => $students,
      '#group' => $group_details,
      '#course' => $course,
      '#selected_topic' => $selected_topic_string,
      '#filter' => $filter,
      '#is_primary_course' => $is_primary_course,
      '#selected_class' => $selected_class,
    ];
  }

}
