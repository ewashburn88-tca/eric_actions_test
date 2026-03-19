<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Student Record Archive entities.
 *
 * @ingroup qwizard
 */
interface QwSRArchiveInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Student Record Archive name.
   *
   * @return string
   *   Name of the Student Record Archive.
   */
  public function getName();

  /**
   * Sets the Student Record Archive name.
   *
   * @param string $name
   *   The Student Record Archive name.
   *
   * @return \Drupal\qwizard\Entity\QwSRArchiveInterface
   *   The called Student Record Archive entity.
   */
  public function setName($name);

  /**
   * Gets the Student Record Archive creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Student Record Archive.
   */
  public function getCreatedTime();

  /**
   * Sets the Student Record Archive creation timestamp.
   *
   * @param int $timestamp
   *   The Student Record Archive creation timestamp.
   *
   * @return \Drupal\qwizard\Entity\QwSRArchiveInterface
   *   The called Student Record Archive entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Student Record Archive current status indicator.
   *
   * Uncurrent Student Record Archive are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Student Record Archive is current.
   */
  public function isCurrent();

  /**
   * Sets the current status of a Student Record Archive.
   *
   * @param bool $current
   *   TRUE to set this Student Record Archive to current, FALSE to set it to uncurrent.
   *
   * @return \Drupal\qwizard\Entity\QwSRArchiveInterface
   *   The called Student Record Archive entity.
   */
  public function setCurrent($current);

}
