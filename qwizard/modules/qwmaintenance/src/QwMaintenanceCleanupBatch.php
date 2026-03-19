<?php

namespace Drupal\qwmaintenance;

/**
 * Batch callbacks for Qwizard archive.
 */
class QwMaintenanceCleanupBatch {

  /**
   * Batch callback to delete orphaned snapshots.
   */
  public static function processOrphanedSnapshots($total_count, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = $total_count;
    }

    // Process in chunks of 50.
    $limit = 50;
    /** @var \Drupal\qwmaintenance\QwDataCleanupManager $manager */
    $manager = \Drupal::service('qwmaintenance.data_cleanup');

    // Always fetch from offset 0 since we are deleting records.
    $snapshots = $manager->getOrphanedSnapshots(FALSE, $limit, 0);

    if (empty($snapshots)) {
      $context['finished'] = 1;
      return;
    }

    foreach ($snapshots as $snapshot) {
      try {
        $snapshot_delete_query = \Drupal::database()->delete('qwiz_snapshot');
        $snapshot_delete_query->condition('id', $snapshot->id);
        $snapshot_delete_query->execute();

        $context['results'][] = $snapshot->id;
        $context['sandbox']['progress']++;
        $context['message'] = t('Deleted orphaned snapshot @id', ['@id' => $snapshot->id]);
      }
      catch (\Throwable $e) {
        $message = $e->getMessage();
        \Drupal::logger('qwmaintenance')->error("Data cleanup for orphaned snapshot {$snapshot->id} failed with error: $message");
      }
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Batch callback to delete orphaned results.
   */
  public static function processOrphanedResults($total_count, &$context) {
    if (empty($context['sandbox'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['max'] = $total_count;
    }

    // Process in chunks of 50.
    $limit = 50;
    /** @var \Drupal\qwmaintenance\QwDataCleanupManager $manager */
    $manager = \Drupal::service('qwmaintenance.data_cleanup');

    // Always fetch from offset 0 since we are deleting records.
    $results = $manager->getOrphanedResults(FALSE, $limit, 0);

    if (empty($results)) {
      $context['finished'] = 1;
      return;
    }

    foreach ($results as $result) {
      try {
        $result_delete_query = \Drupal::database()->delete('qwiz_result');
        $result_delete_query->condition('id', $result->id);
        $result_delete_query->execute();

        $context['results'][] = $result->id;
        $context['sandbox']['progress']++;
        $context['message'] = t('Deleted orphaned result @id', ['@id' => $result->id]);
      }
      catch (\Throwable $e) {
        $message = $e->getMessage();
        \Drupal::logger('qwmaintenance')->error("Data cleanup for orphaned result {$result->id} failed with error: $message");
      }
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    else {
      $context['finished'] = 1;
    }
  }

  /**
   * Batch finished callback for user data archiving.
   */
  public static function orphanedDataCleanupFinished($success, $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Processed data cleanup for @count orphaned record(s).', [
        '@count' => count($results),
      ]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addError(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[1], TRUE),
          ]
        )
      );
    }
  }

}
