<?php

namespace Drupal\qwmaintenance;

use Drupal\Core\Database\Connection;

/**
 * The data cleanup manager.
 */
class QwDataCleanupManager {

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * Constructs a new QwDataCleanupManager instance.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Get orphaned qwiz snapshots.
   *
   * @param bool $return_count
   *   (optional) Whether to return the count or the results. Defaults to FALSE.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 0 (all).
   * @param int $offset
   *   (optional) The offset to start from. Defaults to 0.
   *
   * @return mixed
   *   The count of orphaned snapshots or the results.
   */
  public function getOrphanedSnapshots($return_count = FALSE, $limit = 0, $offset = 0) {
    $query = $this->connection->select('qwiz_snapshot', 'qs');

    if ($return_count) {
      $query->addExpression('COUNT(1)', 'snapshots');
    }
    else {
      $query->fields('qs');
      if ($limit > 0) {
        $query->range($offset, $limit);
      }
    }

    $subquery = $this->connection->select('qwiz_result', 'qr');
    $subquery->addExpression('1');
    $subquery->where('qr.snapshot = qs.id');

    $query->notExists($subquery);

    $result = $query->execute();

    if ($return_count) {
      return $result->fetchField();
    }

    return $result->fetchAll();
  }

  /**
   * Get orphaned qwiz results.
   *
   * @param bool $return_count
   *   (optional) Whether to return the count or the results. Defaults to FALSE.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 0 (all).
   * @param int $offset
   *   (optional) The offset to start from. Defaults to 0.
   *
   * @return mixed
   *   The count of orphaned results.
   */
  public function getOrphanedResults($return_count = FALSE, $limit = 0, $offset = 0) {
    $query = $this->connection->select('qwiz_result', 'qr');

    if ($return_count) {
      $query->addExpression('COUNT(1)', 'results');
    }
    else {
      $query->fields('qr');
      if ($limit > 0) {
        $query->range($offset, $limit);
      }
    }

    $subquery = $this->connection->select('subscription', 's');
    $subquery->addExpression('1');
    $subquery->where('s.id = qr.subscription_id');

    $query->notExists($subquery);

    $result = $query->execute();

    if ($return_count) {
      return $result->fetchField();
    }

    return $result->fetchAll();
  }

}
