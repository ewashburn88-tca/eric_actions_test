<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining Quiz Snapshot entities.
 *
 * @ingroup qwizard
 */
interface QwizSnapshotInterface extends ContentEntityInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Quiz Snapshot json.
   *
   * @return string
   *   Snapshot as json.
   */
  public function getSnapshotJson();

  /**
   * Gets the Quiz Snapshot as php array.
   *
   * @return string
   *   Snapshot as array.
   */
  public function getSnapshotArray();

  /**
   * Sets the Quiz Snapshot name.
   *
   * @param string $json
   *   The Quiz Snapshot json array.
   *
   * @return \Drupal\qwizard\Entity\QwizSnapshotInterface
   *   The called Quiz Snapshot entity.
   */
  public function setSnapshot($json);

}
