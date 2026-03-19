<?php

namespace Drupal\qwizard;

/**
 * Interface MergedQwizInterface.
 */
interface MergedQwizInterface {

  /**
   * Determines if this is a merged qwiz.
   *
   * @return bool
   */
  public function isMergedQwiz();

  /**
   * Returns the component qwiz results.
   *
   * Need all the results from the random quiz questions that have the same
   * topics as the component quiz.
   *
   * @param $result_qwizzes
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getIndividualResults($result_qwizzes, $subscription_id);

  /**
   * Returns the component quiz topics.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getComponentQwizTopics();

  /**
   * Returns the quiz object.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\qwizard\Entity\Qwiz|mixed|null
   */
  public function getQuiz();

}
