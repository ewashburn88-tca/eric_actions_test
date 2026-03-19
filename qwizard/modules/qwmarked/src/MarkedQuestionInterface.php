<?php

namespace Drupal\qwmarked;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a marked_question entity type.
 */
interface MarkedQuestionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

  /**
   * Gets the marked_question title.
   *
   * @return string
   *   Title of the marked_question.
   */
  public function getTitle();

  /**
   * Sets the marked_question title.
   *
   * @param string $title
   *   The marked_question title.
   *
   * @return \Drupal\qwmarked\MarkedQuestionInterface
   *   The called marked_question entity.
   */
  public function setTitle($title);

  /**
   * Gets the marked_question creation timestamp.
   *
   * @return int
   *   Creation timestamp of the marked_question.
   */
  public function getCreatedTime();

  /**
   * Sets the marked_question creation timestamp.
   *
   * @param int $timestamp
   *   The marked_question creation timestamp.
   *
   * @return \Drupal\qwmarked\MarkedQuestionInterface
   *   The called marked_question entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the marked_question status.
   *
   * @return bool
   *   TRUE if the marked_question is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the marked_question status.
   *
   * @param bool $status
   *   TRUE to enable this marked_question, FALSE to disable.
   *
   * @return \Drupal\qwmarked\MarkedQuestionInterface
   *   The called marked_question entity.
   */
  public function setStatus($status);

  /**
   * Gets the course id on the qwiz.
   *
   * @return mixed
   */
  public function getCourseId();

  /**
   * Gets the course on the qwiz.
   *
   * @return mixed
   */
  public function getCourse();

  /**
   * Sets the course id on the qwiz.
   *
   * @param $id
   *
   * @return $this
   */
  public function setCourseId($id);

}
