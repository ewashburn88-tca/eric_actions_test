<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Question Pool entities.
 *
 * @ingroup qwizard
 */
interface QwPoolInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Question Pool name.
   *
   * @return string
   *   Name of the Question Pool.
   */
  public function getName();

  /**
   * Sets the Question Pool name.
   *
   * @param string $name
   *   The Question Pool name.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setName($name);

  /**
   * Gets the Question Pool creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Question Pool.
   */
  public function getCreatedTime();

  /**
   * Sets the Question Pool creation timestamp.
   *
   * @param int $timestamp
   *   The Question Pool creation timestamp.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Question Pool published status indicator.
   *
   * Unpublished Question Pool are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Question Pool is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Question Pool.
   *
   * @param bool $published
   *   TRUE to set this Question Pool to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setPublished($published);

  /**
   * Gets the Question Pool revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Question Pool revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Question Pool revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Question Pool revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Gets the Question Pool course.
   *
   * @return string
   *   Course of the Question Pool.
   */
  public function getCourse();

  /**
   * Sets the Question Pool course.
   *
   * @param string $course
   *   The Question Pool course.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setCourse($course);

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
   * Gets the pool type (entity type).
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   */
  public function getPoolType();

  /**
   * @return mixed
   */
  public function getQuestionCount();

  /**
   * @return mixed
   */
  public function getComplete();

  /**
   * Gets the pool Questions array.
   *
   * @return array
   */
  public function getQuestionsArray();

  /**
   * Retrieves a list of alternative questions, like MMQ (my missed questions).
   *
   * @param $altType
   *
   * @return array
   */
  public function getAlternativeQuestions($altType);

  /**
   * Gets the Quiz Questions json.
   *
   * @return string
   *   Questions as json.
   */
  public function getQuestionsJson();

  /**
   * Sets the Quiz Questions.
   *
   * @param string|array $json
   *   The Quiz Questions json string or array.
   */
  public function setQuestionsJson($json);

  /**
   * Returns all questions in the pool.
   *
   * @return array
   */
  public function getAllQuestions();

  /**
   * Returns the questions left in the pool (Unanswered).
   *
   * Alias of getQuestionsAvailable().
   *
   * @return array
   */
  public function getQuestionsUnanswered();

  /**
   * Returns questions left in the pool (incomplete).
   *
   * Alias of getQuestionsUnanswered().
   *
   * @return array
   */
  public function getQuestionsAvailable();

  /**
   * Returns the questions completed in the pool.
   *
   * @return array
   */
  public function getQuestionsCompleted();

  /**
   * Given a quiz result, this will update the pool statistics.
   *
   * @param $qwiz_result
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface|void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updatePoolStats($qwiz_result);

  /**
   * Sets the questions array.
   *
   * @param array $questions
   *
   * @return mixed
   */
  public function setQuestions(array $questions);

  /**
   * Returns the qwiz ids or objects associated with the pool.
   *
   * @param bool $loaded
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQwizzesInPool($loaded = FALSE);

  /**
   * Returns number of questions in the pool for a particular quiz.
   *
   * @param \Drupal\qwizard\Entity\Qwiz $qwiz
   *
   * @return int|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQuestionsByQwiz(Qwiz $qwiz);

  /**
   * Returns number of questions in the pool for a particular quiz.
   *
   * @param \Drupal\qwizard\Entity\Qwiz $qwiz
   *
   * @return int|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQuestionCountByQwiz(Qwiz $qwiz);

  /**
   * Returns number of correct questions in the pool for a particular quiz.
   *
   * @param \Drupal\qwizard\Entity\Qwiz $qwiz
   *
   * @return int|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCompleteCountByQwiz(Qwiz $qwiz);

}
