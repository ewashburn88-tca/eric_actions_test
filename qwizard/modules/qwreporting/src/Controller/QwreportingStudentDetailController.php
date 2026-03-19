<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\qwreporting\StudentsInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Quiz Wizard Reporting routes.
 */
class QwreportingStudentDetailController extends ControllerBase {

  /**
   * Student service.
   *
   * @var \Drupal\qwreporting\StudentsInterface
   */
  private $students;

  /**
   * The group manager.
   *
   * @var \Drupal\qwreporting\GroupsInterface
   */
  private $groupManager;

  /**
   * QwreportingStudentDetailController constructor.
   *
   * @param \Drupal\qwreporting\StudentsInterface $students
   *   Student object.
   */
  public function __construct(StudentsInterface $students) {
    $this->students = $students;
  }

  /**
   * Get object from container services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container object.
   *
   * @return \Drupal\qwreporting\Controller\QwreportingController|mixed|object|null
   *   Self with injected.
   */
  public static function create(ContainerInterface $container) {
    $student = $container->get('qwreporting.students');
    return new static($student);
  }

  /**
   * Builds the response.
   * Used for /admin/qwreporting/$course_id/$uid/details
   *
   * @param int $course
   *   Course id.
   * @param int $student
   *   Student id.
   *
   * @return array
   *   Theme and variables to twig for rendering.
   */
  public function build($course, $student):array {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $student = User::load($student);

   /* $include_snapshots = false;
    if(isset($_GET['include_snapshots'])){
      $include_snapshots = true;
    }*/
    $include_snapshots = true;

    $specific_subscription = null;
    if(isset($_GET['subscription'])){
      $specific_subscription = $_GET['subscription'];

      // rebuild pools for the old subscription too, might as well
      #$controller = new QWMaintenancePoolsOneUser;
      #$controller->rebuildPools($student->id(), false, true, FALSE, false, false);
    }

    if(isset($_GET['rebuild'])){
      $controller = new QWMaintenancePoolsOneUser;
      $controller->rebuildPools($student->id(), true, true, FALSE, false, true);
    }

    $details = $this->students->getStudentData($course, $student, $include_snapshots, $specific_subscription, NULL, TRUE);
    $subscription_active = (bool) $details['subscription_active'];
    if (!empty($details['data'])) {
      $details['data'] = $this->sortClasses($course, $details['data']);
      // Let's check if we are showing data from expired subscription.
      /*if ($subscription_active && !empty($details['subscription_id'])) {
        \Drupal::messenger()->addWarning(t('Showing data from expired subscription for this student for the course ' . $details['coursename'] . '. Check <a href="/admin/zuku/users/'.$student->id().'/memberships">Memberships</a>'));
      }*/
    }
    else {
      // No data available.
      if ($subscription_active && !empty($details['subscription_id'])) {
        // This means, we found no data for expired subscription.
        \Drupal::messenger()->addWarning(t('No qwpool data in database found for course '.$details['coursename'].' for this student. Check <a href="/admin/zuku/users/'.$student->id().'/memberships">Memberships</a>. '));
      }
      else {
        // This is means no subscription available. But since we are now
        // using expired subscription, this message shouldn't be shown at all.
        \Drupal::messenger()->addWarning(t('No active subscription available for this student for course '.$details['coursename'].'. Check <a href="/admin/zuku/users/'.$student->id().'/memberships">Memberships</a>'));
      }
    }

    // Send extra template info if the viewing user has access to zuku users administration
    $current_user = User::load(\Drupal::currentUser()->id());
    $details['viewing_as_admin'] = false;
    if($current_user->hasPermission('access zukuusers')){
      $details['viewing_as_admin'] = true;
    }

    $details['include_snapshots'] = $include_snapshots;

    // If current user has role 'masquerade', then show a link to masquerade.
    $details['show_masquerade'] = $current_user->hasRole('masquerade');

    return [
      '#theme' => 'qwreporting_student_details',
      '#details' => $details,
    ];
  }

  function sortClasses($course_id, $class_data){
    // Format the data, it needs reordering
    // keep study at the top, then test, then others
    $sorted_class_details = [];
    $statics = \Drupal::service('qwizard.general')->getStatics();
    $study_id = $statics['study_classes'][$course_id][0];
    $test_id = $statics['test_classes'][$course_id][0];
    if(!empty($class_data[$study_id])){
      $sorted_class_details[$study_id] = $class_data[$study_id];
      unset($class_data[$study_id]);
    }
    if(!empty($class_data[$test_id])){
      $sorted_class_details[$test_id] = $class_data[$test_id];
      unset($class_data[$test_id]);
    }

    foreach($class_data as $class_id=>$class){
      $sorted_class_details[$class_id] = $class;
    }
    return $sorted_class_details;
  }

}
