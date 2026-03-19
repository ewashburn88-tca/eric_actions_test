<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Quiz entities.
 *
 * @ingroup qwizard
 */
interface QwizInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Quiz name.
   *
   * @return string
   *   Name of the Quiz.
   */
  public function getName();

  /**
   * Sets the Quiz name.
   *
   * @param string $name
   *   The Quiz name.
   *
   * @return \Drupal\qwizard\Entity\QwizInterface
   *   The called Quiz entity.
   */
  public function setName($name);

  /**
   * Gets the Quiz creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Quiz.
   */
  public function getCreatedTime();

  /**
   * Sets the Quiz creation timestamp.
   *
   * @param int $timestamp
   *   The Quiz creation timestamp.
   *
   * @return \Drupal\qwizard\Entity\QwizInterface
   *   The called Quiz entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets time per questions.
   *
   * @return mixed
   */
  public function getTimePerQuestion();

  /**
   * Sets time per questions.
   *
   * @param int $seconds
   *
   * @return mixed
   */
  public function setTimePerQuestion(int $seconds);

  /**
   * Returns the Quiz published status indicator.
   *
   * Unpublished Quiz are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Quiz is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Quiz.
   *
   * @param bool $published
   *   TRUE to set this Quiz to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\qwizard\Entity\QwizInterface
   *   The called Quiz entity.
   */
  public function setPublished($published);

  /**
   * Gets the Quiz revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Quiz revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\qwizard\Entity\QwizInterface
   *   The called Quiz entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Quiz revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Quiz revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\qwizard\Entity\QwizInterface
   *   The called Quiz entity.
   */
  public function setRevisionUserId($uid);

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

  /**
   * Gets the Qwizard Class taxonomy term on the qwiz.
   *
   * @return mixed
   */
  public function getClass();

  /**
   * Gets the Qwizard Class id on the qwiz.
   *
   * @return mixed
   */
  public function getClassId();

  /**
   * Sets the Qwizard Class id on the qwiz.
   *
   * @param $id
   *
   * @return $this
   */
  public function setClassId($id);

  /**
   * Gets the Qwizard Topics taxonomy term on the qwiz.
   *
   * @return mixed
   */
  public function getTopics();

  /**
   * Gets the Qwizard Topics id on the qwiz.
   *
   * @return mixed
   */
  public function getTopicIds();

  /**
   * Sets the Qwizard Topics id on the qwiz.
   *
   * @param $id
   *
   * @return $this
   */
  public function setTopicId($id);

  /**
   * Gets the pool type for this quiz.
   *
   * @return mixed
   */
  public function getPoolType();

  /**
   * Sets the pool type for this quiz.
   *
   * @param $pool_type
   *
   * @return $this
   */
  public function setPoolType($pool_type);

  /**
   * Function to get questions in quiz.
   *
   * @return array
   */
  public function getQuestionIds();

  /**
   * Returns the number of questions in this quiz.
   *
   * @return int|void
   */
  public function getQuestionCount();

  /**
   * Function to get questions from a quiz based on pool and random.
   *
   * @param int  $num_of_questions
   * @param bool $randomize
   *
   * @return array|bool|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTestQuestions($num_of_questions = 10, $randomize = TRUE);

  /**
   * Initializes a qwiz result at start of quiz take.
   *
   * @param $length
   * @param $subscription_id
   *
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function startQwiz($length, $subscription_id);

  /**
   * Determines if there is an active quiz session for this quiz.
   *
   * @param $subscription_id
   * @param $uid
   *
   * @return \Drupal\qwizard\Entity\QwizResult|NULL
   */
  public function hasActiveSession($subscription_id, $uid);

}
