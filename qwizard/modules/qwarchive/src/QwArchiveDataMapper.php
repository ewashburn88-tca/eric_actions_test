<?php

namespace Drupal\qwarchive;

use Drupal\Core\Database\Connection;

/**
 * The qwarchive data mapper.
 */
class QwArchiveDataMapper {

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * Constructs a new QwArchiveDataMapper instance.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * Add mapping for the entity.
   */
  public function add($uid, $type, $old_id, $new_id) {
    $fields = [
      'uid' => $uid,
      'type' => $type,
      'old_id' => $old_id,
      'new_id' => $new_id,
    ];
    $this->connection->insert('qwarchive_user_data_map')->fields($fields)->execute();
  }

  /**
   * Returns mapping for type & user for entity.
   */
  public function getEntityId($uid, $type, $old_id) {
    $new_id = NULL;
    $query = $this->connection->select('qwarchive_user_data_map', 'd');
    $query->fields('d');
    $query->condition('uid', $uid);
    $query->condition('type', $type);
    $query->condition('old_id', $old_id);
    $query->orderBy('id', 'ASC');
    $result = $query->execute();
    $record = $result->fetchAssoc();

    if (!empty($record['new_id'])) {
      $new_id = $record['new_id'];
    }
    return $new_id;
  }

  /**
   * Remove all mappings per user.
   */
  public function deleteByUser($uid) {
    $query = $this->connection->delete('qwarchive_user_data_map');
    $query->condition('uid', $uid);
    $query->execute();
  }

}
