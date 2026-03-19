<?php

namespace Drupal\qwizard;

/**
 * Interface SiteResultsInterface.
 */
interface SiteResultsInterface {

  /**
   * Gets the total summed quiz results for a quiz.
   *
   * @param      $quiz
   * @param null $start_date
   * @param null $end_date
   *
   * @return array
   */
  public function getResultsForQuiz($quiz, $start_date = NULL, $end_date = NULL);

  /**
   * Gets the total summed quiz results for the user.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param null                         $start_date ISO
   * @param null                         $end_date   ISO
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTotalResults($course, $start_date = NULL, $end_date = NULL);

  /**
   * Gets the total summed quiz results for a pool.
   *
   * @param \Drupal\qwizard\Entity\QwPoolInterface $pool
   * @param null                                   $start_date
   * @param null                                   $end_date
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getResultsForPoolType($pool_type, $start_date = NULL, $end_date = NULL);

  /**
   * Puts the results into an array of totals.
   *
   * @param $qwiz_results
   *     Array of loaded QwResults.
   *
   * @return array
   */
  public function tallyResults($qwiz_results);

  /**
   * Put in cache the averages results.
   *
   * @param $course
   *     $course to query about.
   *
   * @param $type
   *     string type of period.[month,week,day]
   *
   * @param $force
   *     boolean if we force to rebuild the caches.
   *
   * @params $start_date
   *    starting date
   *
   * @params $end_date
   *    End date to query
   *
   * @return array
   */
  public function getResultsForCourses($course, $type, $force, $start_day, $end_day);
}
