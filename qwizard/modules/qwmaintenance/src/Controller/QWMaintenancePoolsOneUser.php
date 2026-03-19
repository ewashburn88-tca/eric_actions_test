<?php

namespace Drupal\qwmaintenance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Drupal\zukuuser\UserActivationProcess;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rebuilds pools for a single user
 */
class QWMaintenancePoolsOneUser extends ControllerBase {

  /**
   * Rebuild the user's pools and redirect back to member home
   */
  public function build($uid) {
    $this->rebuildPools($uid, TRUE, FALSE, FALSE, TRUE, false);

    return $this->redirect('zuku_user_membership_home', ['uid' => $uid]);
  }

  public function rebuildPools($uid, $enable_messaging = true, $only_results = false, $secondary_classes_only = false, $delete_pools_first = false, $active_pools_only = false, $class = null){
    $acct = User::load($uid);
    if(empty($acct)) return;

    $pools_maintenance = \Drupal::service('pools.maintenance');
    $subscriptionHandler = \Drupal::service('qwsubs.subscription_handler');
    $resultsService = \Drupal::service('qwizard.student_results_handler');

    // Rebuild Pools
    if(!$only_results) {
      $pools_maintenance->setUser($uid);
      if(!empty($class_id)){
        $pools_maintenance->setClass($class->id());
      }
      $pools_maintenance->rebuildQuestionPools(true, $secondary_classes_only, $delete_pools_first, $active_pools_only, $enable_messaging);
    }

    // Update Results
    $courses = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('courses', 0, NULL, TRUE);

    foreach ($courses as $course) {
      $subscriptions = $subscriptionHandler->getUserSubscriptions($uid, $course);
      if (!empty($subscriptions)) {
        foreach ($subscriptions as $subscription) {
          if (!empty($subscription)) {
            if(!empty($class_id)){
              $resultsService->rebuildStudentResults($acct, $subscription, $class, false, $secondary_classes_only);
            }else {
              $resultsService->rebuildStudentResults($acct, $subscription, null, true, $secondary_classes_only);
            }

            if($enable_messaging) {
              \Drupal::messenger()->addMessage('Results have been updated for acct=' . $acct->id() . ' subscription=' . $subscription->id());
            }
          }
        }
      }
    }

    \Drupal::logger('qwmaintenance')->notice('Pools & results rebuilt for user '.$uid);
  }

  public function reSaveSnapshots($uid){
    $qwiz_results = \Drupal::entityTypeManager()->getStorage('qwiz_result')->loadByProperties(['user_id' => $uid]);
    $snapshot_ids_to_load = [];
    foreach($qwiz_results as $qwiz_result){
      $snapshot_ids_to_load[] = $qwiz_result->getSnapshotId();
    }
    $snapshots = \Drupal::entityTypeManager()->getStorage('qwiz_snapshot')->loadMultiple($snapshot_ids_to_load);
    foreach($snapshots as $snapshot){
      $original_json = $snapshot->getSnapshotJson();
      $snapshot->setSnapshot($original_json);
      $new_json = $snapshot->getSnapshotJson();

      if($original_json != $new_json){
        $snapshot->save();
        //\Drupal::logger('qwmaintenance')->notice('Snapshot '.$snapshot->id().' needed to be resaved for user '.$uid);
      }
    }
  }
}
