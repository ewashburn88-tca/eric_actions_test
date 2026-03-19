<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Quiz Results entities.
 *
 * @ingroup qwizard
 */
interface QwizResultInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Quiz Results name.
   *
   * @return string
   *   Name of the Quiz Results.
   */
  public function getName();

  /**
   * Sets the Quiz Results name.
   *
   * @param string $name
   *   The Quiz Results name.
   *
   * @return \Drupal\qwizard\Entity\QwizResultInterface
   *   The called Quiz Results entity.
   */
  public function setName($name);

  /**
   * Gets the Quiz Results creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Quiz Results.
   */
  public function getCreatedTime();

  /**
   * Sets the Quiz Results creation timestamp.
   *
   * @param int $timestamp
   *   The Quiz Results creation timestamp.
   *
   * @return \Drupal\qwizard\Entity\QwizResultInterface
   *   The called Quiz Results entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Quiz Results published status indicator.
   *
   * Unpublished Quiz Results are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Quiz Results is published.
   */
  public function isPublished();

  /**
   * Sets the published status of a Quiz Results.
   *
   * @param bool $published
   *   TRUE to set this Quiz Results to published, FALSE to set it to unpublished.
   *
   * @return \Drupal\qwizard\Entity\QwizResultInterface
   *   The called Quiz Results entity.
   */
  public function setPublished($published);

  /**
   * Gets the Quiz Results json.
   *
   * @return string
   *   Results as json.
   */
  public function getCourse();

  /**
   * Sets the Quiz Results.
   *
   * @param string|array $json
   *   The Quiz Results json string or array.
   */
  public function setCourse($course);

  /**
   * Gets the Quiz Results json.
   *
   * @return string
   *   Results as json.
   */
  public function getClass($loaded);

  /**
   * Sets the Quiz Results.
   *
   * @param string|array $json
   *   The Quiz Results json string or array.
   */
  public function setClass($class);

  /**
   * Get quiz attached to result.
   *
   * @return mixed
   */
  public function getQuiz();

  /**
   * Get quiz id attached to result.
   *
   * @return mixed
   */
  public function getQuizId();

  /**
   * Get quiz id attached to result.
   *
   * @return mixed
   */
  public function getQuizJSONLabel();

  /**
   * Get quiz revision id (vid) attached to result.
   *
   * @return mixed
   */
  public function getQuizRev();

  /**
   * Set quiz id attached to result.
   *
   * @param $uid
   *
   * @return mixed
   */
  public function setQuizId($qid);

  /**
   * Set quiz revision attached to result.
   *
   * @param $revision
   *
   * @return mixed
   */
  public function setQuizRev($revision);

  /**
   * Set quiz id attached to result.
   *
   * @param \Drupal\qwizard\Entity\QuizInterface $quiz
   *
   * @return mixed
   */
  public function setQuiz(QwizInterface $quiz);

  /**
   * Get end time.
   *
   * @return mixed
   */
  public function getEndTime();

  /**
   * Sets end time.
   *
   * @param $time
   *
   * @return $this
   * @throws \Exception
   */
  public function setEndTime($time);

  /**
   * Get reviewed time.
   *
   * @return mixed
   */
  public function getReviewedTime();

  /**
   * Sets reviewed time.
   *
   * @param $time
   *
   * @return $this
   * @throws \Exception
   */
  public function setReviewedTime($time);

  /**
   * Get Correct.
   *
   * @return mixed
   */
  public function getCorrect();

  /**
   * Get Attempted.
   *
   * @return mixed
   */
  public function getAttempted();

  /**
   * Get Seen.
   *
   * @return mixed
   */
  public function getSeen();

  /**
   * Get Total Questions.
   *
   * @return mixed
   */
  public function getTotalQuestions();

  /**
   * Returns the snapshot of this as an array.
   *
   * @return array
   */
  public function getSnapshotArray();

  /**
   * Gets pool for this quiz result.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getResultPool();

  /**
   * Records results from answering a single question.
   *
   * @param bool $correct
   * @param      $question_id
   *
   * @return bool
   */
  public function scoreQuestion($correct, $question_id, $question_idx);

  /**
   * Records end time.
   *
   * Any QwizResult that has an end time is considered complete.
   */
  public function endQwizResult($rebuild_results_after = false);

}
