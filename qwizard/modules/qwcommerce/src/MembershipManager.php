<?php

namespace Drupal\qwcommerce;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qwizard\CourseHandlerInterface;
use Drupal\qwizard\MembershipHandlerInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\qwsubs\SubscriptionHandler;
use Psr\Log\LoggerInterface;

/**
 * QWCommerce Membership manager.
 */
class MembershipManager {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The messenger.
   */
  protected MessengerInterface $messenger;

  /**
   * The membership handler.
   */
  protected MembershipHandlerInterface $membershipHandler;

  /**
   * The subscription handler.
   */
  protected SubscriptionHandler $subscriptionHandler;

  /**
   * The course handler.
   */
  protected CourseHandlerInterface $courseHandler;

  /**
   * Constructs a MembershipManager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\qwizard\MembershipHandler\MembershipHandlerInterface $membership_handler
   *   The membership handler.
   * @param \Drupal\qwsubs\SubscriptionHandler $subscription_handler
   *   The subscription handler.
   * @param \Drupal\qwizard\CourseHandlerInterface $course_handler
   *   The course handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, MessengerInterface $messenger, MembershipHandlerInterface $membership_handler, SubscriptionHandler $subscription_handler, CourseHandlerInterface $course_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger= $logger_factory->get('qwcommerce');
    $this->messenger = $messenger;
    $this->membershipHandler = $membership_handler;
    $this->subscriptionHandler = $subscription_handler;
    $this->courseHandler = $course_handler;
  }

  /**
   * Create user subscription.
   */
  public function createSubscription($product_variation, $account, $start = 'now', $end = NULL, $show_messages = FALSE) {
    $response = [
      'uid' => $account->id(),
      'product_variation_id' => $product_variation->id(),
    ];
    // Flag to update order state.
    $update_order_state = FALSE;
    // Set account to user.
    $this->membershipHandler->setAcct($account);
    $variation_type = $product_variation->bundle();

    if ($variation_type == 'course_membership' || $variation_type == 'course_extension' || $variation_type == 'upgrade_membership') {
      $title = $product_variation->getTitle();
      $product = $product_variation->getProduct();
      // Override the variation_type, variation types are not to be trusted.
      $variation_type = $product->bundle();
      $response['variation_type'] = $variation_type;

      $course = $product->get('field_course')->entity;
      // Set the course for membership.
      $this->membershipHandler->setCourse($course);

      $response['course'] = $course->id();

      // Set premium for membership.
      $premium = $product->get('field_premium')->getString();
      $this->membershipHandler->setPremium($premium);

      // Default course duration ($max_term for createsNewMembershipForExistingSubscription).
      $course_duration = 335;
      if ($variation_type == 'course_membership' || $variation_type == 'course_extension') {
        // If the prod variation has a fixed end date, set course duration
        // by that.
        // Check has value in field_membership_end_date.
        $is_fixed_date = FALSE;
        if (!empty($product_variation->field_membership_end_date->value)) {
          $now_date = new \Datetime();
          $end_date = new \Datetime($product_variation->field_membership_end_date->value);
          // Get the revert date.
          if (!empty($product_variation->field_revert_to_term_date->value)) {
            $revert_date = new \Datetime($product_variation->field_revert_to_term_date->value);
          }
          else {
            $revert_date = clone $end_date;
          }
          $is_fixed_date = $revert_date > $now_date && $end_date > $now_date;
          // Get diff between now and field_membership_end_date.
          $fixed_interval = $now_date->diff($end_date);
        }
        if ($is_fixed_date) {
          // Get the days till the fixed date.
          $course_duration = $fixed_interval->days;
        }
        else {
          $course_duration = QwizardGeneral::getCourseDurationForProductVariationTermID($product_variation->get('attribute_term')->getString(), TRUE, $start, $end);
          $this->membershipHandler->setCourseDuration($course_duration);
        }
      }

      // Check to see if the user has this course already active.
      $membership_exists = $this->membershipHandler->isUserSubscribedToCourse($course->id(), TRUE);
      // Set created flag for subscription. Defaults to false.
      $subscription_created_flag = FALSE;

      if ($variation_type == 'upgrade_membership') {
        // The order was placed to upgrade the membership. Let's make the
        // subscription premium.
        // Give the user premium role.
        $this->membershipHandler->addRolesToUser($course->id(), $account->id(), TRUE);
        // Get current subscription of the user.
        $current_subscription = $this->subscriptionHandler->getCurrentSubscription($course, $account->id());
        // Make it premium & save.
        $current_subscription->setPremium(1);
        $current_subscription->save();
        // Set up the subscription flag.
        $subscription_created_flag = TRUE;
        $response['subscription_id'] = $current_subscription->id();
      }
      elseif (!$membership_exists && $variation_type == 'course_membership') {
        // Create user's first subscription to this course.
        $subscription_created = $this->membershipHandler->createNewSubscription($start, $end);
        $response['subscription_id'] = $subscription_created->id();
        // Set up the subscription flag.
        $subscription_created_flag = TRUE;
      }
      else {
        if (!$membership_exists) {
          // Activate most recent subscription, it should exist at this point.
          $last_subscription = $this->subscriptionHandler->getCurrentSubscription($course, $account->id(), NULL, TRUE);
          $this->membershipHandler->reactivateSubscription($last_subscription->id());
          $response['subscription_id'] = $last_subscription->id();
        }

        // Extensions.
        // Adjust logic depending on if most recent
        // old membership is expired or not.
        $active_memberships_to_course = $this->membershipHandler->getUserMemberships(TRUE, $course->id());

        if (!empty($active_memberships_to_course)) {
          // Increase - User has an active subscription. Extend the duration
          // of recent subscription & expire old subscriptions.
          $recent_membership = $this->membershipHandler->getMembership($active_memberships_to_course[0]);
          $recent_membership_end = $recent_membership->get('end')->getValue();
          $remaining_membership_duration = QwizardGeneral::estimateLengthFromDates(strtotime('now'), $recent_membership_end[0]['value']);

          $course_duration = $course_duration + $remaining_membership_duration;

          // Cap duration at 1 year (365 days) to prevent infinite accumulation.
          if ($course_duration > 365) {
            $course_duration = 365;
          }

          $this->membershipHandler->setCourseDuration($course_duration);

          if ($show_messages) {
            $this->messenger->addMessage($this->t('Your subscription has been extended to @duration days.', [
              '@duration' => $course_duration,
            ]));
          }

          // Expire existing subscriptions for this course.
          foreach ($active_memberships_to_course as $old_membership_id) {
            $this->membershipHandler->endMembership($old_membership_id);
          }
        }
        // Any remaining time on their current subscription should be added to
        // what they are purchasing and they get a new subscription & pools.
        $current_subscription = $this->subscriptionHandler->getCurrentSubscription($course, $account->id());
        $this->membershipHandler->createsNewMembershipForExistingSubscription($current_subscription->id(), $course_duration, 'Renew membership', FALSE, $start, $end);
        $response['subscription_id'] = $current_subscription->id();

        // Handle the premium extensions.
        if ($premium) {
          $this->membershipHandler->addRolesToUser($course->id(), $account->id(), TRUE);
          $current_subscription->setPremium(1);
          $current_subscription->save();
        }
        // Set order created flag for subscription. Defaults to false.
        $subscription_created_flag = TRUE;
      }

      if ($subscription_created_flag) {
        if (!$this->membershipHandler->isUserSubscribedToCourse($course->id(), TRUE)) {
          // Even if subscription is created, somehow the user is not
          // subscribed to course yet. This should not happen.
          $this->logger->error('Error creating new subscription: user=' . $account->id() . ' course=' . $course->id());
          // Throw an exception so we can handle the problem appropriately.
          throw new \Exception('Error creating new subscription: user=' . $account->id() . ' course=' . $course->id());
        }
        else {
          // Rebuild the pools again to make sure it is synced.
          $qw_maintenance_pools = new QWMaintenancePoolsOneUser;
          $qw_maintenance_pools->rebuildPools($account->id(), FALSE, FALSE, FALSE, TRUE, TRUE);
          // Set the flag to update order state. The calling function will be
          // responsible to check the flag & update the order state.
          $update_order_state = TRUE;
          // Set current course cookie to new course.
          $this->courseHandler->setCurrentCourse($course);
          // Add product id to response. It is used to send email when order
          // is placed.
          $response['product_id'] = $product->id();
        }
        $response['product_variation_sku'] = $product_variation->getSku();
        $response['product_variation'] = $product_variation;
      }
      $response['product_type'] = $variation_type;
    }
    $response['update_order_state'] = $update_order_state;
    return $response;
  }

}
