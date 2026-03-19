<?php

namespace Drupal\qwizard;

use Drupal\qwizard\Entity\QwizInterface;

/**
 * Interface QwizSessionHandlerInterface.
 */
interface QwizSessionHandlerInterface {

  /**
   * Initializes a quiz result and starts a quiz.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface|null $qwiz
   * @param null                                      $length
   * @param string                                    $alt_type
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\qwizard\RedirectResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function initializeQuiz(QwizInterface $qwiz = NULL, $length = NULL, $alt_type = 'standard', $onlyMarked = false, $post_payload = []);

}
