<?php

namespace Drupal\qwschools\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface for defining School entities.
 *
 * @ingroup qwschools
 */
interface SchoolInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the School name.
   *
   * @return string
   *   Name of the School.
   */
  public function getName();

  /**
   * Sets the School name.
   *
   * @param string $name
   *   The School name.
   *
   * @return \Drupal\qwschools\Entity\SchoolInterface
   *   The called School entity.
   */
  public function setName($name);

  /**
   * Gets the School creation timestamp.
   *
   * @return int
   *   Creation timestamp of the School.
   */
  public function getCreatedTime();

  /**
   * Sets the School creation timestamp.
   *
   * @param int $timestamp
   *   The School creation timestamp.
   *
   * @return \Drupal\qwschools\Entity\SchoolInterface
   *   The called School entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the School revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the School revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\qwschools\Entity\SchoolInterface
   *   The called School entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the School revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the School revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\qwschools\Entity\SchoolInterface
   *   The called School entity.
   */
  public function setRevisionUserId($uid);

}
