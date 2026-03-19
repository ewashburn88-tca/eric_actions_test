<?php

namespace Drupal\qwarchive;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\qwsubs\Entity\SubscriptionInterface;

/**
 * The qwarchive restore manager.
 */
class QwArchiveRestoreManager {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $recordManager;

  /**
   * The qwarchive storage manager.
   */
  protected QwArchiveStorageManager $storageManager;

  /**
   * The data mapper.
   */
  protected QwArchiveDataMapper $dataMapper;

  /**
   * Constructs a new QwArchiveRestoreManager instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $record_manager
   *   The qwarchive record manager.
   * @param \Drupal\qwarchive\QwArchiveStorageManager $storage_manager
   *   The qwarchive storage manager.
   * @param \Drupal\qwarchive\QwArchiveDataMapper $data_mapper
   *   The data mapper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QwArchiveRecordManager $record_manager, QwArchiveStorageManager $storage_manager, QwArchiveDataMapper $data_mapper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->recordManager = $record_manager;
    $this->storageManager = $storage_manager;
    $this->dataMapper = $data_mapper;
  }

  /**
   * Restore student results.
   */
  public function restoreStudentResult($account) {
    // We are not archiving the student results. So there isn't need to restore
    // them. Instead student results can be rebuilt.
    // @todo rebuild student results here.
  }

  /**
   * Restore qwiz results.
   */
  public function restoreQwizResult($account) {
    $record = $this->recordManager->getRecordItemForUser($account->id(), 'qwiz_result');

    $filename = $record['filename'];
    if (empty($filename)) {
      // No file available. No need to proceed. But we can't mark restore as
      // failed.
      return TRUE;
    }
    $data = $this->readJson($filename, $account->id());

    // The qwiz results has reference to the subscriptions. We need to make
    // sure if subscriptions exists before proceeding. There is a possibility
    // of subscriptions archived.
    $validated = $this->checkQwizResultSubscriptions($data, $account);

    if ($validated) {
      // Storage for qwiz_snapshot entity type.
      $qw_snapshot_storage = $this->entityTypeManager->getStorage('qwiz_snapshot');
      // Storage for qwiz_result entity type.
      $qw_result_storage = $this->entityTypeManager->getStorage('qwiz_result');
      // Actually restore the data.
      foreach ($data as $record) {
        // First create snapshot since snapshot has reference in qw_result.
        $snapshot = $record['snapshot'];
        $qw_snapshot = $qw_snapshot_storage->create($snapshot);
        $qw_snapshot->save();

        $result = $record['result'];
        // Update the snapshot reference.
        $result['snapshot'] = $qw_snapshot->id();
        // Get valid subscription id.
        $new_sub_id = $this->getMappedSubscriptionId($result['subscription_id'], $account->id());
        $result['subscription_id'] = $new_sub_id;
        // Create the entity directly from values.
        $qw_result = $qw_result_storage->create($result);
        $qw_result->save();
      }
      // We have restored the data. Let's delete the unwanted file.
      $this->storageManager->deleteJsonFile($filename, $account->id());

    }
    return TRUE;
  }

  /**
   * Restore qwiz pools.
   */
  public function restoreQwizPools($account) {
    $record = $this->recordManager->getRecordItemForUser($account->id(), 'qwiz_pools');

    $filename = $record['filename'];
    if (empty($filename)) {
      // No file available. No need to proceed. But we can't mark restore as
      // failed.
      return TRUE;
    }

    $data = $this->readJson($filename, $account->id());

    // The qwiz pools has reference to the subscriptions. We need to make
    // sure if subscriptions exists before proceeding. There is a possibility
    // of subscriptions archived.
    $validated = $this->checkQwizPoolSubscriptions($data, $account);

    if ($validated) {
      // Storage for qwpool entity type.
      $qw_pool_storage = $this->entityTypeManager->getStorage('qwpool');

      foreach ($data as $record) {
        $new_sub_id = $this->getMappedSubscriptionId($record['subscription_id'], $account->id());
        $record['subscription_id'] = $new_sub_id;

        $questions = $record['questions'];
        // Remove questions for now. We will set it later.
        unset($record['questions']);
        // Create the entity directly from values.
        $qw_pool = $qw_pool_storage->create($record);
        // Set the questions. The json conversion is handled in function itself.
        $qw_pool->setQuestionsJson($questions);
        $qw_pool->save();
      }
    }
    // We have restored the data. Let's delete the unwanted file.
    $this->storageManager->deleteJsonFile($filename, $account->id());
    return TRUE;
  }

  /**
   * Restore subscriptions.
   */
  public function restoreSubscriptions($account) {
    $record = $this->recordManager->getRecordItemForUser($account->id(), 'subscriptions');

    $filename = $record['filename'];
    if (empty($filename)) {
      // No file available. No need to proceed. But we can't mark restore as
      // failed.
      return TRUE;
    }
    $data = $this->readJson($filename, $account->id());

    $subscription_storage = $this->entityTypeManager->getStorage('subscription');
    $sub_term_storage = $this->entityTypeManager->getStorage('subterm');

    // Let's restore the data.
    foreach ($data as $subscription_data) {
      $sub_terms = $subscription_data['sub_terms'];
      $old_subscription_id = $subscription_data['id'];
      // Create subscription first.
      unset($subscription_data['sub_terms']);
      unset($subscription_data['id']);

      $subscription = $subscription_storage->create($subscription_data);
      $subscription->save();

      // Save the mapping data.
      $this->dataMapper->add($account->id(), 'subscription', $old_subscription_id, $subscription->id());

      // Create sub terms.
      if (!empty($sub_terms)) {
        foreach ($sub_terms as $sub_term_data) {
          $sub_term_data['subscription_id'] = $subscription->id();
          $sub_term = $sub_term_storage->create($sub_term_data);
          $sub_term->save();
        }
      }
    }
    // We have restored the data. Let's delete the unwanted file.
    $this->storageManager->deleteJsonFile($filename, $account->id());
  }

  /**
   * Validates subscriptions referred in archived qwiz results.
   */
  protected function checkQwizResultSubscriptions($data, $account) {
    $subscription_ids = [];
    foreach ($data as $record) {
      $result = $record['result'];
      if (!empty($result['subscription_id'])) {
        $subscription_ids[$result['subscription_id']] = $result['subscription_id'];
      }
      $snapshot = $record['snapshot'];
      if (!empty($snapshot['subscription_id'])) {
        $subscription_ids[$snapshot['subscription_id']] = $snapshot['subscription_id'];
      }
    }

    foreach ($subscription_ids as $id) {
      $valid_sub_id = $this->getMappedSubscriptionId($id, $account->id());
      if (empty($valid_sub_id)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Validates subscriptions referred in archived qwiz pools.
   */
  protected function checkQwizPoolSubscriptions($data, $account) {
    $subscription_ids = [];
    foreach ($data as $record) {
      if (!empty($record['subscription_id'])) {
        $subscription_ids[$record['subscription_id']] = $record['subscription_id'];
      }
    }

    foreach ($subscription_ids as $id) {
      $valid_sub_id = $this->getMappedSubscriptionId($id, $account->id());
      if (empty($valid_sub_id)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get mapped subscription id.
   */
  protected function getMappedSubscriptionId($id, $uid) {
    // First load the existing subscription.
    $subscription = $this->entityTypeManager->getStorage('subscription')->load($id);
    if ($subscription instanceof SubscriptionInterface) {
      // Don't worry about checking in mapping. Just return the id.
      return $subscription->id();
    }
    // Check in data mapper.
    $new_sub_id = $this->dataMapper->getEntityId($uid, 'subscription', $id);
    if (empty($new_sub_id)) {
      return FALSE;
    }
    $subscription = $this->entityTypeManager->getStorage('subscription')->load($new_sub_id);
    if ($subscription instanceof SubscriptionInterface) {
      return $subscription->id();
    }
    return FALSE;
  }

  /**
   * Get json data.
   */
  protected function readJson($filename, $uid) {
    return $this->storageManager->readJsonFile($filename, $uid);
  }

  /**
   * Returns callbacks required for data types.
   */
  public function getDataTypeCallbacks() {
    // Below order is important. The callbacks must execute in given order to
    // maintain the references. E.g. The qwiz results have reference to
    // subscription, so to properly restore the qwiz results, the subscriptions
    // must be restored first.
    return [
      'student_result' => 'restoreStudentResult',
      'subscriptions' => 'restoreSubscriptions',
      'qwiz_result' => 'restoreQwizResult',
      'qwiz_pools' => 'restoreQwizPools',
    ];
  }

}
