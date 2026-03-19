<?php
/**
 * @file
 */

namespace Drupal\qwizard\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes update user import tasks for Zuku import module.
 *
 * @QueueWorker(
 *   id = "qw_cache_getOthersResults_queue_worker",
 *   title = @Translation("Qw Cache getOthersResults: Queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class QwizardCacheGetOthersResultsQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $QwCache = \Drupal::service('qwizard.cache');
    $QwCache->buildOthersProgressCache($data['course_id']);
  }

}
