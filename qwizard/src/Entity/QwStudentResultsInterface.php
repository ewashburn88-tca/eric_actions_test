<?php

namespace Drupal\qwizard\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Student Results entities.
 *
 * @ingroup qwizard
 */
interface QwStudentResultsInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Student Results name.
   *
   * @return string
   *   Name of the Student Results.
   */
  public function getName();

  /**
   * Sets the Student Results name.
   *
   * @param string $name
   *   The Student Results name.
   *
   * @return \Drupal\qwizard\Entity\QwStudentResultsInterface
   *   The called Student Results entity.
   */
  public function setName($name);

  /**
   * Gets the Student Results creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Student Results.
   */
  public function getCreatedTime();

  /**
   * Sets the Student Results creation timestamp.
   *
   * @param int $timestamp
   *   The Student Results creation timestamp.
   *
   * @return \Drupal\qwizard\Entity\QwStudentResultsInterface
   *   The called Student Results entity.
   */
  public function setCreatedTime($timestamp);


  /**
   * Gets the Quiz Results json.
   *
   *   Results as json.
   */
  public function getResultsJson($type = 'array');

  /**
   * Sets the Quiz Results.
   *
   * @param string|array $json
   *   The Quiz Results json string or array.
   */
  public function setResults($json);

  /**
   * Rebuilds all student result records for course.
   */
  public function rebuildResults();
}
