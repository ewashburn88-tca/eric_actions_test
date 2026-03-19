<?php

namespace Drupal\qwmaintenance\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\qwizard\QwStudentResultsHandler;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\Entity\User;

/**
 * Plugin implementation of the student_results_rebuild queueworker.
 *
 * @QueueWorker (
 *   id = "qwmaintenance_queue",
 *   title = @Translation("Queue for various qwmaintenance tasks"),
 *   cron = {"time" = 180}
 * )
 */
class QWMaintenanceQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    // Process item operations.
    if (!empty($data->operations)) {
      $controller = new QWMaintenancePoolsOneUser;
      // Rebuild Question Pools
      $operations = array_values($data->operations);
      $active_operations = [];
      foreach ($operations as $operation) {
        if (!empty($operation)) {
          $active_operations[$operation] = $operation;
        }
      }

      if (in_array('Rebuild Results', $active_operations)) {
        try {
          $controller->rebuildPools($data->uid, FALSE, TRUE, FALSE, FALSE, TRUE);
        }
        catch (\Exception $e) {
          \Drupal::logger('qwmaintenance')
            ->error('Failure for ' . $data->uid . ' on rebuildResults: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        }
      }

      if (in_array('Rebuild Question Pools', $active_operations)) {
        try {
          $controller->rebuildPools($data->uid, FALSE, FALSE, FALSE);
        }
        catch (\Exception $e) {
          \Drupal::logger('qwmaintenance')
            ->error('Failure for ' . $data->uid . ' on rebuildPools: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        }
      }
      // Delete Pools and Rebuild Question Pools
      if (in_array('Delete Pools and Rebuild Question Pools', $active_operations)) {
        try {
          $controller->rebuildPools($data->uid, FALSE, FALSE, FALSE, TRUE);
        }
        catch (\Exception $e) {
          \Drupal::logger('qwmaintenance')
            ->error('Failure for ' . $data->uid . ' on rebuildPools: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        }
      }
      // Rebuild secondary Question Pools
      if (in_array('Rebuild secondary Question Pools', $active_operations)) {
        try {
          $controller->rebuildPools($data->uid, FALSE, FALSE, TRUE);
        }
        catch (\Exception $e) {
          \Drupal::logger('qwmaintenance')
            ->error('Failure for ' . $data->uid . ' on rebuildPools: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        }
      }

      // Resave Snapshots
      if (in_array('Resave Snapshots', $active_operations)) {
        try {
          $controller->resaveSnapshots($data->uid);
        }
        catch (\Exception $e) {
          \Drupal::logger('qwmaintenance')
            ->error('Failure for ' . $data->uid . ' on Resave Snapshots: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
        }
      }
    }
  }

}
