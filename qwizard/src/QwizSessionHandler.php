<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\qwizard\Entity\QwizInterface;
use Drupal\qwsubs\SubscriptionHandler;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class QwizSessionHandler.
 */
class QwizSessionHandler implements QwizSessionHandlerInterface {

  /**
   * Constructs a new QwizSessionHandler object.
   */
  public function __construct() {

  }

  /**
   * Initializes a quiz result and starts a quiz.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface|null $qwiz
   * @param null                                      $length
   * @param string                                    $alt_type
   *
   * @return \Drupal\Core\Entity\EntityInterface|RedirectResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function initializeQuiz(QwizInterface $qwiz = NULL, $length = NULL, $alt_type = 'standard', $onlyMarked = false, $post_payload = []) {
    $course = $qwiz->getCourse();

    // Get user's current subid.
    $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
    $sub = $subscriptions_service->getCurrentSubscription($course);
    if (empty($sub)) {
      // @todo: Add exception handling for patch no active subscription.
      \Drupal::logger('QwizSessionHandler')->error('No current sub for quiz init on qwiz '.$qwiz->id().' with payload of '.json_encode($post_payload));
      throw new \Exception();
    }
    $sub_id = $sub->id();
    // Determine if qwiz is already open.
    if ($qwiz_result = $qwiz->hasActiveSession($sub_id, $sub->getOwnerId())) {
      // @todo: What should be do here, for now we're just going to close.
      $qwiz_result->endQwizResult(true);
      $params = ['@quiz' => $qwiz->label(), '@qr_id' => $qwiz_result->id(), '$qr_name' => $qwiz_result->getName()];
      \Drupal::logger('QwizSession')
        ->notice('Ended abandoned session for @quiz, QR id @qr_id - $qr_name', $params);
    }

    $qwiz_result = $qwiz->startQwiz($length, $sub_id, $alt_type, $onlyMarked);
    if (empty($qwiz_result->getTotalQuestions()) && ($alt_type == 'standard' || $alt_type == 'normal')) {
      $params = ['@quiz' => $qwiz->label(), '@post' => json_encode($post_payload), '@qrid' => $qwiz_result->id(), '@length' => $length];
      \Drupal::logger('QwizSession')
        ->error('Quiz result has no questions for @quiz. POST params were @post. QwizResult ID was @qrid. Length was @length.', $params);
      $qwiz_result->endQwizResult(false);
    }

    return $qwiz_result;
  }

  /**
   * Determine if a quiz has an active session.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface $qwiz
   * @param                                      $subscription_id
   * @param                                      $uid
   *
   * @return \Drupal\qwizard\Entity\QwizResult|NULL
   */
  public static function qwizHasActiveSession(QwizInterface $qwiz, $subscription_id, $uid) {

    return $qwiz->hasActiveSession($subscription_id, $uid);
  }
}
