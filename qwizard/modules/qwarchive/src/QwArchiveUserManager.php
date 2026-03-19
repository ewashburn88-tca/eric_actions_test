<?php

declare(strict_types=1);

namespace Drupal\qwarchive;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Service to manage inactive users.
 */
class QwArchiveUserManager {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * Constructs a QwArchiveUserManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->entityTypeManager = $entity_type_manager;
    $this->connection = $connection;
  }

  /**
   * Get inactive users based on the threshold.
   *
   * @param string|int $inactive_threshold
   *   The threshold string (e.g. 'since 2023') or number of days.
   * @param array $filtered_uids
   *   Optional array of UIDs to filter by.
   * @param bool $status
   *   User account status.
   * @param int $limit
   *   Optional limit for the number of users to return.
   * @param bool $use_pager
   *   Whether to use pager or not.
   *
   * @return \Drupal\user\UserInterface[]
   *   An array of user entities.
   */
  public function getInactiveUsers(string|int $inactive_threshold, array $filtered_uids = [], bool|NULL $status = NULL, int $limit = 20, bool $use_pager = TRUE): array {
    if (is_int($inactive_threshold) || is_numeric($inactive_threshold)) {
      $threshold_timestamp = strtotime("-{$inactive_threshold} days");
    }
    else {
      $threshold_timestamp = strtotime($inactive_threshold);
      if (!$threshold_timestamp) {
        return [];
      }
    }

    // Select users who are inactive for N days.
    $query = $this->connection->select('users_field_data', 'u');
    $query->fields('u', ['uid']);
    $query->condition('u.login', $threshold_timestamp, '<');
    // Skip the users that never logged in.
    $query->condition('u.login', 0, '!=');
    // Ensure we don't pick up anonymous user.
    $query->condition('u.uid', 0, '>');
    if (!is_null($status)) {
      $query->condition('u.status', $status);
    }

    // Exclude users already in the archive.
    $query->leftJoin('qwarchive_user_archive', 'qa', 'u.uid = qa.uid');
    $query->isNull('qa.uid');

    // Filter by specific UIDs if provided.
    if (!empty($filtered_uids)) {
      $query->condition('u.uid', $filtered_uids, 'IN');
    }

    // Order the results using last login time.
    $query->orderBy('u.login', 'DESC');

    // Add pager.
    if ($use_pager) {
      /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
      $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender');
      $query->limit($limit);
    }
    else {
      $query->range(0, $limit);
    }
    $result = $query->execute()->fetchCol();

    if (empty($result)) {
      return [];
    }

    return $this->entityTypeManager->getStorage('user')->loadMultiple($result);
  }

  /**
   * Get bulk data counts for users to avoid N+1 queries.
   *
   * @param array $uids
   *   Array of user IDs.
   *
   * @return array
   *   Associative array of counts keyed by UID.
   */
  public function getBulkDataCounts(array $uids): array {
    if (empty($uids)) {
      return [];
    }
    $counts = [];

    // Initialize defaults.
    foreach ($uids as $uid) {
      $counts[$uid] = [
        'student_results' => 0,
        'qwiz_results' => 0,
        'qwiz_pools' => 0,
        'subscriptions' => 0,
      ];
    }

    // 1. Count Student Results.
    $query = $this->connection->select('qw_student_results_field_data', 'q');
    $query->fields('q', ['user_id']);
    $query->addExpression('COUNT(id)', 'cnt');
    $query->condition('user_id', $uids, 'IN');
    $query->groupBy('user_id');
    $results = $query->execute()->fetchAllKeyed();
    foreach ($results as $uid => $count) {
      $counts[$uid]['student_results'] = $count;
    }

    // 2. Count Qwiz Results (using qwiz_result table as per QwArchiveManager).
    $query = $this->connection->select('qwiz_result', 'q');
    $query->fields('q', ['user_id']);
    $query->addExpression('COUNT(id)', 'cnt');
    $query->condition('user_id', $uids, 'IN');
    $query->groupBy('user_id');
    $results = $query->execute()->fetchAllKeyed();
    foreach ($results as $uid => $count) {
      $counts[$uid]['qwiz_results'] = $count;
    }

    // 3. Count Pools (All pools for user).
    $query = $this->connection->select('qwpool', 'p');
    $query->fields('p', ['user_id']);
    $query->addExpression('COUNT(id)', 'cnt');
    $query->condition('user_id', $uids, 'IN');
    $query->groupBy('user_id');
    $results = $query->execute()->fetchAllKeyed();
    foreach ($results as $uid => $count) {
      $counts[$uid]['qwiz_pools'] = $count;
    }

    // 4. Count Inactive Subscriptions (status <> 1).
    $query = $this->connection->select('subscription', 's');
    $query->fields('s', ['user_id']);
    $query->addExpression('COUNT(id)', 'cnt');
    $query->condition('user_id', $uids, 'IN');
    $query->condition('status', 1, '<>');
    $query->groupBy('user_id');
    $results = $query->execute()->fetchAllKeyed();
    foreach ($results as $uid => $count) {
      $counts[$uid]['subscriptions'] = $count;
    }

    return $counts;
  }

  /**
   * Filter UIDs to return only those that have data to archive.
   *
   * @param array $uids
   *   Array of user IDs.
   *
   * @return array
   *   Array of user IDs that have data.
   */
  public function getUsersWithData(array $uids): array {
    $counts = $this->getBulkDataCounts($uids);
    $uids_with_data = [];
    foreach ($counts as $uid => $data) {
      // Check if any value is > 0.
      if (array_sum($data) > 0) {
        $uids_with_data[$uid] = $uid;
      }
    }
    return $uids_with_data;
  }

  /**
   * Check if user has data to archive.
   *
   * @param int $uid
   *   The user id.
   *
   * @return bool
   *   TRUE if user has data, FALSE otherwise.
   */
  public function userHasDataToArchive(int $uid): bool {
    $result = $this->getUsersWithData([$uid]);
    return !empty($result);
  }

}
