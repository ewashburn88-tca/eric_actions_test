<?php

namespace Drupal\qwarchive;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\qwizard\Entity\QwPool;
use Drupal\qwizard\QwStudentResultsHandlerInterface;
use Drupal\qwsubs\SubscriptionHandlerInterface;

/**
 * The qwarchive manager.
 */
class QwArchiveManager {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The file system.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The student results handler.
   */
  protected QwStudentResultsHandlerInterface $qwStudentResultsHandler;

  /**
   * The subscription handler.
   */
  protected SubscriptionHandlerInterface $subscriptionHandler;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $recordManager;

  /**
   * The qwarchive storage manager.
   */
  protected QwArchiveStorageManager $storageManager;

  /**
   * Constructs a new QwArchiveManager instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\qwizard\QwStudentResultsHandlerInterface $qw_student_results_handler
   *   The student results handler.
   * @param \Drupal\qwsubs\SubscriptionHandlerInterface $subscription_handler
   *   The membership handler.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $record_manager
   *   The qwarchive record manager.
   * @param \Drupal\qwarchive\QwArchiveStorageManager $storage_manager
   *   The qwarchive storage manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, QwStudentResultsHandlerInterface $qw_student_results_handler, SubscriptionHandlerInterface $subscription_handler, QwArchiveRecordManager $record_manager, QwArchiveStorageManager $storage_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->qwStudentResultsHandler = $qw_student_results_handler;
    $this->subscriptionHandler = $subscription_handler;
    $this->recordManager = $record_manager;
    $this->storageManager = $storage_manager;
  }

  /**
   * Archive student results.
   */
  public function archiveStudentResult($account) {
    try {
      $count = 0;
      // Get student result ids.
      $results = $this->getStudentResultsToArchive($account);
      if (empty($results)) {
        $this->recordManager->markItemAsNoDataFound($account->id(), 'student_result', 'No data found to archive.');
        return TRUE;
      }
      // Load student results.
      $student_results = $this->entityTypeManager->getStorage('qw_student_results')->loadMultiple($results);
      if (!empty($student_results)) {
        foreach ($student_results as $student_result) {
          // Student results can be rebuilt. So instead of archiving the data,
          // we will just delete it.
          $student_result->delete();
          $count++;
        }
      }
    }
    catch (\Throwable $e) {
      $this->recordManager->markItemAsFailed($account->id(), 'student_result', 'Exception: ' . $e->getMessage());
      return FALSE;
    }
    $this->recordManager->markItemAsCompleted($account->id(), 'student_result', 'Deleted ' . $count . ' record(s).');
    return TRUE;
  }

  /**
   * Archive qwiz results.
   */
  public function archiveQwizResult($account) {
    $filename = NULL;
    try {
      $records = $this->getQwizResultsToArchive($account);
      if (empty($records)) {
        $this->recordManager->markItemAsNoDataFound($account->id(), 'qwiz_result', 'No data found to archive.');
        return TRUE;
      }
      $archive_data = [];
      $qwiz_result_ids = [];
      $qwiz_snapshot_ids = [];
      $count = 0;

      // Extract all snapshot IDs first to avoid N+1 queries.
      $snapshot_ids = array_map(function ($record) {
        return $record['snapshot'];
      }, $records);

      // Bulk load snapshots.
      $snapshots = [];
      if (!empty($snapshot_ids)) {
        $snapshot_query = $this->connection->select('qwiz_snapshot', 's');
        $snapshot_query->fields('s');
        $snapshot_query->condition('id', $snapshot_ids, 'IN');
        $snapshots = $snapshot_query->execute()->fetchAllAssoc('id');
      }

      foreach ($records as $record) {
        $qwiz_result_ids[] = $record['id'];
        $archive = [];
        // Remove unnecessary data from record.
        unset($record['id']);
        unset($record['uuid']);
        unset($record['langcode']);
        $archive['result'] = $record;

        // Get snapshot from pre-loaded data.
        $snapshot_id = $record['snapshot'];
        if (!isset($snapshots[$snapshot_id])) {
          // Should not happen, but safeguard.
          continue;
        }

        $snapshot = (array) $snapshots[$snapshot_id];
        $qwiz_snapshot_ids[] = $snapshot['id'];

        $snapshot_json = $snapshot['snapshot'];
        // Convert json data into array so we can store in uniform json.
        $snapshot_data = Json::decode($snapshot_json);
        $archive['snapshot'] = $snapshot_data;
        // Add to archive data.
        $archive_data[] = $archive;

        $count++;
      }
      // We have all the data available now. Write the json file.
      $filename = $this->createJson($archive_data, $account->id(), 'qwiz_result');

      // Delete the qwiz results.
      $qwiz_delete_query = $this->connection->delete('qwiz_result');
      $qwiz_delete_query->condition('id', $qwiz_result_ids, 'IN');
      $qwiz_delete_query->execute();

      // Delete the qwiz snapshots.
      $snapshot_delete_query = $this->connection->delete('qwiz_snapshot');
      $snapshot_delete_query->condition('id', $qwiz_snapshot_ids, 'IN');
      $snapshot_delete_query->execute();
    }
    catch (\Throwable $e) {
      $this->recordManager->markItemAsFailed($account->id(), 'qwiz_result', 'Exception: ' . $e->getMessage());
      return FALSE;
    }
    $this->recordManager->markItemAsCompleted($account->id(), 'qwiz_result', 'Archived ' . $count . ' result(s).', $filename);
    return TRUE;
  }

  /**
   * Archive qwiz pools.
   */
  public function archiveQwizPools($account) {
    $filename = NULL;
    try {
      $pools = $this->getQwizPoolsToArchive($account);
      if (empty($pools)) {
        $this->recordManager->markItemAsNoDataFound($account->id(), 'qwiz_pools', 'No data found to archive.');
        return TRUE;
      }
      $count = 0;
      $pool_data = [];
      foreach ($pools as $pool) {
        // Only use required values.
        $pool_data[] = [
          'type' => $pool->get('type')->getString(),
          'user_id' => $pool->getOwnerId(),
          'subscription_id' => $pool->get('subscription_id')->getString(),
          'name' => $pool->getName(),
          'status' => $pool->isPublished(),
          'decrement' => $pool->get('decrement')->getString(),
          'decr_wrong' => $pool->get('decr_wrong')->getString(),
          'decr_skipped' => $pool->get('decr_skipped')->getString(),
          'course' => $pool->getCourseId(),
          'class' => $pool->getClassId(),
          'total_questions' => $pool->get('total_questions')->getString(),
          'complete' => $pool->getComplete(),
          'questions' => $pool->getQuestionsArray(),
        ];
      }
      // Write the json file.
      $filename = $this->createJson($pool_data, $account->id(), 'qwiz_pools');

      // Now delete the pools.
      foreach ($pools as $pool) {
        $pool->delete();
        $count++;
      }
      // Also delete from revision table.
      $delete_query = $this->connection->delete('qwpool_revision');
      $delete_query->condition('user_id', $account->id());
      $delete_query->execute();
    }
    catch (\Throwable $e) {
      $this->recordManager->markItemAsFailed($account->id(), 'qwiz_pools', 'Exception: ' . $e->getMessage());
      return FALSE;
    }
    $this->recordManager->markItemAsCompleted($account->id(), 'qwiz_pools', 'Archived ' . $count . ' pool(s).', $filename);
    return TRUE;
  }

  /**
   * Archive subscriptions.
   */
  public function archiveSubscriptions($account) {
    $filename = NULL;
    try {
      if (!$this->moduleHandler->moduleExists('qwsubs')) {
        $this->recordManager->markItemAsFailed($account->id(), 'subscriptions', 'qwsubs module is not installed.');
        return FALSE;
      }

      // Get all user subscriptions.
      // This does not have current active subscriptions.
      $subscriptions = $this->getSubscriptionsToArchive($account);
      if (empty($subscriptions)) {
        $this->recordManager->markItemAsNoDataFound($account->id(), 'subscriptions', 'No data found to archive.');
        return TRUE;
      }

      $count = 0;
      $subs_to_delete = [];
      $terms_to_delete = [];
      $subscription_data = [];
      foreach ($subscriptions as $subscription) {
        // Subscription json data, if any.
        $sub_data = $subscription->get('data')->getString();
        $data = !empty($sub_data) ? Json::decode($sub_data) : [];
        // Also get the sub-terms.
        $subterms = $subscription->getSubTerms(FALSE);
        // Prepare subterm data.
        $subterm_data = [];
        foreach ($subterms as $sub_term) {
          $subterm_data[] = [
            'user_id' => $sub_term->getOwnerId(),
            'comment' => $sub_term->getComment(),
            'start' => $sub_term->getStart(),
            'end' => $sub_term->getEnd(),
          ];
          $terms_to_delete[] = $sub_term;
        }
        $subscription_data[] = [
          'id' => $subscription->id(),
          'type' => $subscription->bundle(),
          'user_id' => $subscription->getOwnerId(),
          'name' => $subscription->getName(),
          'status' => $subscription->isActive(),
          'max_term' => $subscription->get('max_term')->getString(),
          'course' => $subscription->getCourseId(),
          'data' => $data,
          'premium' => $subscription->getPremium(),
          'extension_limit' => $subscription->get('extension_limit')->getString(),
          'sub_terms' => $subterm_data,
        ];
        $subs_to_delete[] = $subscription;
        $count++;

      }
      if (empty($subs_to_delete)) {
        $this->recordManager->markItemAsNoDataFound($account->id(), 'subscriptions', 'No data found to archive.');
        return TRUE;
      }
      // Write the json file.
      $filename = $this->createJson($subscription_data, $account->id(), 'subscriptions');

      // Now delete the sub-terms.
      foreach ($terms_to_delete as $sub_term) {
        $sub_term->delete();
      }
      // Delete the subscriptions.
      foreach ($subs_to_delete as $sub) {
        $sub->delete();
        // Also delete from revision table.
        $delete_query = $this->connection->delete('subscription_revision');
        $delete_query->condition('user_id', $account->id());
        $delete_query->execute();
      }
    }
    catch (\Throwable $e) {
      $this->recordManager->markItemAsFailed($account->id(), 'subscriptions', 'Exception: ' . $e->getMessage());
      return FALSE;
    }
    $this->recordManager->markItemAsCompleted($account->id(), 'subscriptions', 'Archived ' . $count . ' subscription(s).', $filename);
    return TRUE;
  }

  /**
   * Get student result ids that will be archived for user.
   */
  public function getStudentResultsToArchive($account) {
    $results = $this->qwStudentResultsHandler->getStudentResults($account);
    return $results;
  }

  /**
   * Get qwiz results that will be archived for user.
   */
  public function getQwizResultsToArchive($account) {
    $query = $this->connection->select('qwiz_result', 'q');
    $query->fields('q');
    $query->condition('user_id', $account->id());
    $records = $query->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    return $records;
  }

  /**
   * Get qwiz pools that will be archived for user.
   */
  public function getQwizPoolsToArchive($account) {
    $pools = QwPool::getPoolsForUser($account->id(), NULL, FALSE, TRUE);
    if (empty($pools)) {
      // Convert to array to maintain consistency with other data.
      $pools = [];
    }
    return $pools;
  }

  /**
   * Get subscriptions for user (excludes current active subscriptions).
   */
  public function getSubscriptionsToArchive($account) {
    $subscriptions = $this->subscriptionHandler->getUserSubscriptions($account->id());
    $subscription_data = [];
    foreach ($subscriptions as $subscription) {
      // We don't want to archive current active subsctiption.
      if ($subscription->isActive()) {
        // Skip the active subscription.
        continue;
      }
      $subscription_data[] = $subscription;
    }
    return $subscription_data;
  }

  /**
   * Get all data to archive.
   */
  public function getAllDataToArchive($account) {
    return [
      'student_results' => $this->getStudentResultsToArchive($account),
      'qwiz_results' => $this->getQwizResultsToArchive($account),
      'qwiz_pools' => $this->getQwizPoolsToArchive($account),
      'subscriptions' => $this->getSubscriptionsToArchive($account),
    ];
  }

  /**
   * Returns callbacks required for data types.
   */
  public function getDataTypeCallbacks() {
    return [
      'student_result' => 'archiveStudentResult',
      'qwiz_result' => 'archiveQwizResult',
      'qwiz_pools' => 'archiveQwizPools',
      'subscriptions' => 'archiveSubscriptions',
    ];
  }

  /**
   * Stores data into json file.
   */
  protected function createJson($data, $uid, $name) {
    $filename = $name . '-' . time();
    // Sanitize filename.
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    $this->storageManager->storeJsonFile($filename, $data, $uid);
    return $filename;
  }

}
