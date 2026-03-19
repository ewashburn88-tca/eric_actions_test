<?php

namespace Drupal\qwarchive;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;

/**
 * The qwarchive record manager.
 */
class QwArchiveRecordManager {

  use StringTranslationTrait;

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The data mapper.
   */
  protected QwArchiveDataMapper $qwArchiveDataMapper;

  /**
   * Record status: queued for processing.
   */
  const STATUS_QUEUED = 'queued';

  /**
   * Record status: currently in progress.
   */
  const STATUS_IN_PROGRESS = 'in_progress';

  /**
   * Record status: processing failed.
   */
  const STATUS_FAILED = 'failed';

  /**
   * Record status: processing completed successfully.
   */
  const STATUS_COMPLETED = 'completed';

  /**
   * Data type status: not archived.
   */
  const TYPE_STATUS_NOT_ARCHIVED = 0;

  /**
   * Data type status: archived.
   */
  const TYPE_STATUS_ARCHIVED = 1;

  /**
   * Data type status: failed.
   */
  const TYPE_STATUS_FAILED = 2;

  /**
   * Data type status: no_data_found.
   */
  const TYPE_STATUS_NO_DATA_FOUND = 3;

  /**
   * Restore status: queued for processing.
   */
  const RESTORE_STATUS_QUEUED = 'queued';

  /**
   * Restore status: currently in progress.
   */
  const RESTORE_STATUS_IN_PROGRESS = 'in_progress';

  /**
   * Restore status: processing failed.
   */
  const RESTORE_STATUS_FAILED = 'failed';

  /**
   * Restore status: processing completed successfully.
   */
  const RESTORE_STATUS_COMPLETED = 'completed';

  /**
   * Constructs a new QwArchiveRecordManager instance.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\qwarchive\QwArchiveDataMapper $qwarchive_data_mapper
   *   The data mapper.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager, TimeInterface $time, LoggerChannelFactoryInterface $logger_factory, QwArchiveDataMapper $qwarchive_data_mapper) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->logger = $logger_factory->get('qwarchive');
    $this->qwArchiveDataMapper = $qwarchive_data_mapper;
  }

  /**
   * Get all available statuses with their labels.
   */
  public function getStatuses() {
    return [
      self::STATUS_QUEUED => $this->t('Queued'),
      self::STATUS_IN_PROGRESS => $this->t('In Progress'),
      self::STATUS_FAILED => $this->t('Failed'),
      self::STATUS_COMPLETED => $this->t('Completed'),
    ];
  }

  /**
   * Get all available statuses for data types with their labels.
   */
  public function getDataTypeStatuses() {
    return [
      self::TYPE_STATUS_NOT_ARCHIVED => $this->t('Not Archived'),
      self::TYPE_STATUS_ARCHIVED => $this->t('Archived'),
      self::TYPE_STATUS_FAILED => $this->t('Failed'),
      self::TYPE_STATUS_NO_DATA_FOUND => $this->t('No Data Found'),
    ];
  }

  /**
   * Get all available restore statuses with their labels.
   */
  public function getRestoreStatuses() {
    return [
      self::RESTORE_STATUS_QUEUED => $this->t('Queued'),
      self::RESTORE_STATUS_IN_PROGRESS => $this->t('In Progress'),
      self::RESTORE_STATUS_FAILED => $this->t('Failed'),
      self::RESTORE_STATUS_COMPLETED => $this->t('Completed'),
    ];
  }

  /**
   * Get a specific status label.
   */
  public function getStatusLabel($status) {
    $statuses = $this->getStatuses();
    return $statuses[$status] ?? $status;
  }

  /**
   * Get a specific restore status label.
   */
  public function getRestoreStatusLabel($status) {
    $statuses = $this->getRestoreStatuses();
    return $statuses[$status] ?? $status;
  }

  /**
   * Add new record using given status.
   */
  public function add($uid, $data_types = [], $type = 'batch') {
    $record['uid'] = $uid;
    $record['status'] = $type == 'batch' ? self::STATUS_IN_PROGRESS : self::STATUS_QUEUED;
    $record['created'] = $this->time->getRequestTime();
    $record_id = $this->connection->insert('qwarchive_user_archive')->fields($record)->execute();

    // Add an entry for each item.
    foreach ($data_types as $type) {
      $item = [
        'archive_id' => $record_id,
        'uid' => $uid,
        'type' => $type,
        'status' => self::TYPE_STATUS_NOT_ARCHIVED,
      ];
      $this->connection->insert('qwarchive_user_archive_item')->fields($item)->execute();
    }
  }

  /**
   * Add new restore request using given status.
   */
  public function addRestoreRequest($uid, $type = 'batch', $comment = '') {
    $record['uid'] = $uid;
    $record['status'] = $type == 'batch' ? self::RESTORE_STATUS_IN_PROGRESS : self::RESTORE_STATUS_QUEUED;
    $record['comment'] = $comment;
    $record['created'] = $this->time->getRequestTime();
    $this->connection->insert('qwarchive_user_restore')->fields($record)->execute();

    // Log the event for added restore request.
    $this->logger->info('The restore request is successfully added for user @uid to be processed via @type.', [
      '@uid' => $uid,
      '@type' => $type,
    ]);
  }

  /**
   * Get available user ids.
   */
  public function getArchivedUserIds() {
    $query = $this->connection->select('qwarchive_user_archive', 'a');
    $query->fields('a', ['uid']);
    $result = $query->execute()->fetchCol();

    return !empty($result) ? $result : [];
  }

  /**
   * Get available records from database.
   */
  public function getRecords($conditions = [], $pager_limit = NULL) {
    $query = $this->connection->select('qwarchive_user_archive', 'a');

    $query->fields('a');
    foreach ($conditions as $condition) {
      $operator = !empty($condition['operator']) ? $condition['operator'] : '=';
      $query->condition($condition['field'], $condition['value'], $operator);
    }
    $query->orderBy('created', 'DESC');

    // Support for pager since this function can be used to show records in UI.
    if ($pager_limit) {
      /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
      $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
      $query->limit($pager_limit);
    }

    $result = $query->execute();
    $records = $result->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    return $records;
  }

  /**
   * Check if user has record.
   */
  public function userHasArchivedData($uid) {
    $conditions = [
      [
        'field' => 'uid',
        'value' => $uid,
      ],
    ];
    $records = $this->getRecords($conditions);
    return !empty($records);
  }

  /**
   * Get available restore requests from database.
   */
  public function getRestoreRequests($conditions = [], $pager_limit = NULL) {
    $query = $this->connection->select('qwarchive_user_restore', 'r');

    $query->fields('r');
    foreach ($conditions as $condition) {
      $operator = !empty($condition['operator']) ? $condition['operator'] : '=';
      $query->condition($condition['field'], $condition['value'], $operator);
    }
    $query->orderBy('created', 'DESC');

    // Support for pager since this function can be used to show records in UI.
    if ($pager_limit) {
      /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
      $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
      $query->limit($pager_limit);
    }

    $result = $query->execute();
    $records = $result->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
    return $records;
  }

  /**
   * Get a restore request of user.
   */
  public function getRestoreRequest($uid) {
    $query = $this->connection->select('qwarchive_user_restore', 'r');
    $query->fields('r');
    $query->condition('uid', $uid);
    $query->orderBy('id', 'ASC');
    $query->range(0, 1);
    $result = $query->execute();
    $record = $result->fetchAssoc();
    return $record;
  }

  /**
   * Get available record items from archive id.
   */
  public function getRecordItems($archive_id, $index_key = 'id') {
    $query = $this->connection->select('qwarchive_user_archive_item', 'a');
    $query->fields('a');
    $query->condition('archive_id', $archive_id);
    $query->orderBy('id', 'ASC');
    $result = $query->execute();
    $records = $result->fetchAllAssoc($index_key, \PDO::FETCH_ASSOC);
    return $records;
  }

  /**
   * Get single record item for user & type.
   */
  public function getRecordItemForUser($uid, $type) {
    $query = $this->connection->select('qwarchive_user_archive_item', 'a');
    $query->fields('a');
    $query->condition('uid', $uid);
    $query->condition('type', $type);
    $query->orderBy('id', 'ASC');
    $query->range(0, 1);
    $result = $query->execute();
    $record = $result->fetchAssoc();
    return $record;
  }

  /**
   * Change item status.
   */
  public function changeRecordStatus($uid, $status, $message = '') {
    $this->connection->update('qwarchive_user_archive')
      ->fields([
        'status' => $status,
        'comment' => $message,
      ])
      ->condition('uid', $uid)
      ->execute();
  }

  /**
   * Mark specific record as failed.
   */
  public function markArchivalAsFailed($uid, $message = '') {
    $this->changeRecordStatus($uid, self::STATUS_FAILED, $message);
  }

  /**
   * Mark specific record as archived.
   */
  public function markArchivalAsCompleted($uid, $message = '') {
    $this->changeRecordStatus($uid, self::STATUS_COMPLETED, $message);
    // Add role to the user to indicate the user data is archived.
    $rid = 'archived';
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!($account instanceof UserInterface) || $account->hasRole($rid)) {
      return;
    }
    // For efficiency manually save the original account before applying
    // any changes.
    $account->original = clone $account;
    $account->addRole($rid);
    $account->save();

    // Log the event for completed archival.
    $this->logger->info('The data is successfully archived for user @uid.', [
      '@uid' => $uid,
    ]);
  }

  /**
   * Change item status.
   */
  public function changeItemStatus($uid, $type, $status, $message = '', $filename = NULL) {
    $fields = [
      'status' => $status,
      'comment' => $message,
    ];
    if (!empty($filename)) {
      $fields['filename'] = $filename;
    }
    $this->connection->update('qwarchive_user_archive_item')
      ->fields($fields)
      ->condition('uid', $uid)
      ->condition('type', $type)
      ->execute();
  }

  /**
   * Mark specific record type as failed.
   */
  public function markItemAsFailed($uid, $type, $message = '') {
    $this->changeItemStatus($uid, $type, self::TYPE_STATUS_FAILED, $message);
  }

  /**
   * Mark specific record type as archived.
   */
  public function markItemAsCompleted($uid, $type, $message = '', $filename = NULL) {
    $this->changeItemStatus($uid, $type, self::TYPE_STATUS_ARCHIVED, $message, $filename);
  }

  /**
   * Mark specific record type as no data found.
   */
  public function markItemAsNoDataFound($uid, $type, $message = '') {
    $this->changeItemStatus($uid, $type, self::TYPE_STATUS_NO_DATA_FOUND, $message);
  }

  /**
   * Remove archive item for user.
   */
  public function removeItem($uid, $type) {
    $query = $this->connection->delete('qwarchive_user_archive_item');
    $query->condition('uid', $uid);
    $query->condition('type', $type);
    $query->execute();
  }

  /**
   * Remove archive record for user.
   */
  public function removeRecord($uid, $remove_items = FALSE, $remove_role = TRUE, $skip_log = FALSE) {
    $query = $this->connection->delete('qwarchive_user_archive');
    $query->condition('uid', $uid);
    $query->execute();

    if ($remove_items) {
      $query = $this->connection->delete('qwarchive_user_archive_item');
      $query->condition('uid', $uid);
      $query->execute();
    }
    // Since we process this after restoring the data, we will also remove
    // any restore request made by the user since the data has been restored.
    $query = $this->connection->delete('qwarchive_user_restore');
    $query->condition('uid', $uid);
    $query->execute();

    // Remove data mappings as those are no longer needed after data restore.
    $this->qwArchiveDataMapper->deleteByUser($uid);

    if ($remove_role) {
      // Remove role from the user to indicate the user data is restored.
      $rid = 'archived';
      $account = $this->entityTypeManager->getStorage('user')->load($uid);
      if (!($account instanceof UserInterface) || !$account->hasRole($rid)) {
        return;
      }
      // For efficiency manually save the original account before applying
      // any changes.
      $account->original = clone $account;
      $account->removeRole($rid);
      $account->save();
    }

    if (!$skip_log) {
      // Log the event for restore.
      $this->logger->info('The data is successfully restored for user @uid.', [
        '@uid' => $uid,
      ]);
    }
  }

}
