<?php

namespace Drupal\qwreporting;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\qwizard\MembershipHandlerInterface;
use Drupal\qwizard\QwizardGeneralInterface;
use Drupal\qwsubs\SubscriptionHandler;
use Psr\Log\LoggerInterface;

/**
 * Groups operations.
 */
class QwreportingGroupOps {

  /**
   * The session manager.
   */
  protected SessionManagerInterface $sessionManager;

  /**
   * The module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The subscription handler.
   */
  protected SubscriptionHandler $subscriptionHandler;

  /**
   * The qwizard general.
   */
  protected QwizardGeneralInterface $qwizardGeneral;

  /**
   * The membership handler.
   */
  protected MembershipHandlerInterface $membershipHandler;

  /**
   * Constructs new QwreportingGroupOps.
   *
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   *   The session manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\qwsubs\SubscriptionHandler $subscription_handler
   *   The subscription handler.
   * @param \Drupal\qwizard\QwizardGeneralInterface $qwizard_general
   *   The qwizard general.
   * @param \Drupal\qwizard\MembershipHandlerInterface $membership_handler
   *   The membership handler.
   */
  public function __construct(SessionManagerInterface $session_manager, ModuleHandlerInterface $module_handler, LoggerChannelFactoryInterface $logger_factory, SubscriptionHandler $subscription_handler, QwizardGeneralInterface $qwizard_general, MembershipHandlerInterface $membership_handler) {
    $this->sessionManager = $session_manager;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger_factory->get('qwreporting');
    $this->subscriptionHandler = $subscription_handler;
    $this->qwizardGeneral = $qwizard_general;
    $this->membershipHandler = $membership_handler;
  }

  /**
   * Sets user account status.
   */
  public function changeUserAccountStatus($account, $user_status) {
    $account->set('status', $user_status);
    $account->save();
  }

  /**
   * Logs user out.
   */
  public function logoutUser($account) {
    $this->logger->notice('Session closed for %name.', ['%name' => $account->getAccountName()]);
    $this->moduleHandler->invokeAll('user_logout', [$account]);
    $this->sessionManager->delete($account->id());
  }

  /**
   * Ends user subscription.
   */
  public function endSubscription($account, $course_id) {
    $subscription = $this->subscriptionHandler->getCurrentSubscription($course_id, $account->id());
    if (!empty($subscription)) {
      $this->subscriptionHandler->cancelSubscription($subscription->id());
    }
  }

  /**
   * Extends user membership.
   */
  public function extendSubscription($account, $value, $course_id, $type) {
    $subscription = $this->subscriptionHandler->getCurrentSubscription($course_id, $account->id());
    if ($subscription) {
      $this->membershipHandler->setAcctByUID($account->id());
      $membership_ids = $this->membershipHandler->getUserMemberships(TRUE, $course_id, $subscription->id());
      // Usually it should return single membership id, still let's loop through
      // the ids to extend the memberships.
      if (!empty($membership_ids)) {
        foreach ($membership_ids as $membership_id) {
          $this->membershipHandler->extendMembership($membership_id, $value, $type);
        }
      }
    }
  }

  /**
   * Sets premium status of subscription.
   */
  public function changeAccountPremium($account, $premium_status, $course_id) {
    $subscription = $this->subscriptionHandler->getCurrentSubscription($course_id, $account->id());
    if (!empty($subscription)) {
      $subscription->setPremium($premium_status);
      $subscription->save();
      // Update premium role of user.
      $course_roles = $this->qwizardGeneral->getStatics('full_course_roles');
      if (!empty($course_roles[$course_id]['premium'])) {
        $premium_role = $course_roles[$course_id]['premium'];
        if ($premium_status) {
          // User should have premium role if not added already.
          if (!$account->hasRole($premium_role)) {
            $account->addRole($premium_role);
            $account->save();
          }
        }
        else {
          // User should not have premium role.
          if ($account->hasRole($premium_role)) {
            $account->removeRole($premium_role);
            $account->save();
          }
        }
      }
    }
  }

  /**
   * Sets special status of subscription.
   */
  public function changeAccountSpecial($account, $special_status) {
    $has_special_role = $account->hasRole('special_product');

    if ($special_status && !$has_special_role) {
      // Add special role.
      $account->addRole('special_product');
      $account->save();
    }

    if (!$special_status && $has_special_role) {
      // Remove special role.
      $account->removeRole('special_product');
      $account->save();
    }
  }

}
