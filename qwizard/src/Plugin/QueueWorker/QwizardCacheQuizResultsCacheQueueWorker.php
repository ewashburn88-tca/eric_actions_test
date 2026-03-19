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
 *   id = "qw_cache_quizResultsCache_queue_worker",
 *   title = @Translation("Qw Cache quizResultsCache: Queue worker"),
 *   cron = {"time" = 120}
 * )
 */
class QwizardCacheQuizResultsCacheQueueWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $QwCache = \Drupal::service('qwizard.cache');
    $QwCache->buildClassCache($data['course_id'], $data['class_id']);
  }

}
