<?php

namespace Drupal\qwmarked\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\qwizard\QwStudentResultsHandler;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\Entity\User;

/**
 * Plugin implementation of the setMarkedQuestionCourseWhereNullForUser_QueueWorker queueworker.
 *
 * @QueueWorker (
 *   id = "setMarkedQuestionCourseWhereNullForUser_QueueWorker",
 *   title = @Translation("Set Marked Question Course Where Null For User QueueWorker"),
 *   cron = {"time" = 240}
 * )
 */
class setMarkedQuestionDefaultsQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = null;
    if(!empty($data['uid'])){
      $uid = $data['uid'];
    }
    elseif(!empty($data->uid)){
      $uid = $data->uid;
    }
    if(empty($uid)) return;
    $user = User::load($uid);
    if(empty($user)) return;

    \Drupal::service('qwmarked.setMarkedQuestionDefaultClass')->setForUser($user);
  }
}
