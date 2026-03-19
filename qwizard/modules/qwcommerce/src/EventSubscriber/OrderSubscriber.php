<?php

namespace Drupal\qwcommerce\EventSubscriber;

use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\qwcommerce\MembershipManager;
use Drupal\qwizard\QwizardGeneral;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles subscriptions with an order's workflow.
 */
class OrderSubscriber implements EventSubscriberInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The membership manager.
   *
   * @var \Drupal\qwcommerce\MembershipManager
   */
  protected $membershipManager;

  /**
   * Constructs a new OrderSubscriber object.
   */
  public function __construct(RequestStack $request_stack, AccountInterface $current_user, MailManagerInterface $mail_manager, LoggerChannelFactoryInterface $logger_factory, MembershipManager $membership_manager) {
    $this->requestStack = $request_stack;
    $this->currentUser = $current_user;
    $this->mailManager = $mail_manager;
    $this->logger = $logger_factory->get('qwcommerce');
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'commerce_order.place.post_transition' => 'placePostTrans',
      OrderEvents::ORDER_PAID => 'onPaid',
      'commerce_order.place.pre_transition' => ['onPlace', 100],
      OrderEvents::ORDER_ASSIGN => ['onAssign', 0],
      'commerce_order.cancel.post_transition' => ['onCancel', -100],
    ];
  }

  /**
   * Activates the subscriptions on order paid.
   */
  public function onPaid(OrderEvent $event) {
    /*$order = $event->getEntity();
    $order_number = $order->getNumber();
    $this->logger->notice('Order Paid ' . $order_number);*/
  }

  /**
   * Reacts to an order being placed.
   *
   * Handles subscriptions at the product variation type level.
   */
  public function onPlace(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $state = $order->getState();
    $order_state = $state->value;
    // @todo make sure that adding sub on 'validation' step is ok
    if ($order_state == 'completed' || $order_state == 'validate' || $order_state == 'validation') {
      $this->addSubscription($event);
    }
  }

  /**
   * Reacts to assignment of order ownership to new user.
   */
  public function onAssign(OrderAssignEvent $event) {}

  /**
   * Reacts after the transition of the order.
   */
  public function placePostTrans(WorkflowTransitionEvent $event) {}

  /**
   * Reacts to an order being cancelled.
   */
  public function onCancel(WorkflowTransitionEvent $event) {}

  /**
   * Adds subscription to user after purchase.
   */
  protected function addSubscription($event) {
    $order = $event->getEntity();
    $account = $order->getCustomer();

    // Add log.
    $this->logger->notice('Adding subscription for user ' . $account->id());

    // Check if owner matches current user?
    if ($account->id() != $this->currentUser->id()) {
      // @todo Use correct handling for user or admin.
      $this->logger->warning('Account ID mismatch during subscription. Account: ' . $account->id() . ', Current: ' . $this->currentUser->id());
      return;
    }

    foreach ($order->getItems() as $order_item) {
      $product_variation = $order_item->getPurchasedEntity();
      try {
        $response = $this->membershipManager->createSubscription($product_variation, $account, 'now', NULL, TRUE);
      }
      catch (\Exception $e) {
        $this->logger->error('Subscription creation failed: @message', ['@message' => $e->getMessage()]);
        throw new \RuntimeException('We\'re sorry, but your order was unsuccessful. Please contact us.');
      }
      // Change order state. This was as per old code.
      $update_order_state = $response['update_order_state'];
      if ($update_order_state) {
        // Move order to "complete" state, by moving it to "validate" state
        // here. Commerce then moves it to completed. !empty check added to
        // account for users buying multiple products at once.
        $order_state = $order->getState();
        $order_state_transitions = $order_state->getTransitions();
        if (!empty($order_state_transitions['validate'])) {
          $order_state->applyTransition($order_state_transitions['validate']);
        }
      }

      // Handle email.
      if (!empty($response['product_id'])) {
        $product_id = $response['product_id'];

        // Send product email.
        $this->sendEmail($account, $product_id);
      }

      if (!empty($response['product_variation_sku'])) {
        $product_variation_sku = $response['product_variation_sku'];
        // Send product variation email.
        $this->sendEmail($account, $product_variation_sku);
      }
    }
  }

  /**
   * Sends out an email.
   */
  protected function sendEmail($account, $id = NULL) {
    if (empty($id)) {
      // Skip the email.
      return;
    }

    // Initialize common variables used in emails.
    $actually_send = (QwizardGeneral::getCurrentEnv('number') < 1);
    // $actually_send = TRUE; // uncomment to force email to send.
    $user_path_to_admin = $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() . Url::fromRoute('zuku_user_membership_home', ['uid' => $account->id()])->toString();
    // Language code.
    $langcode = 'en';

    $title = $this->getProductEmailTitle($id);
    if (!empty($title)) {
      // Only send email if we have title. This way, we are limiting an email
      // for specific products / variations.
      $this->logger->notice('An order for ' . $title . ' has been placed by ' . $account->getEmail() . '. UID=' . $account->id());
      $params['subject'] = $title . ' Subscription - ' . $account->getEmail();
      $params['body'] = '<p>A ' . $title . ' subscription order has been placed for ' . $account->getEmail() . '. Their User ID is <a href="' . $user_path_to_admin . '">' . $account->id() . '</a></p>';

      $result = $this->mailManager->mail('zukuuser', 'mail', 'support@zukureview.com', $langcode, $params, $actually_send);
      if ($result['result'] !== TRUE) {
        $this->logger->error('There was a problem sending @title email to @email', [
          '@title' => $title,
          '@email' => 'support@zukureview.com',
        ]);
      }
    }
  }

  /**
   * Returns the title to be used in email.
   */
  protected function getProductEmailTitle($id) {
    // Mapping for product id or variation sku & title to be used in email.
    $mapping = [
      35 => 'VTNE TCC',
      38 => 'VTNE MSU',
      40 => 'NAVLE MSU',
      41 => 'NAVLE VCA',
      42 => 'VTNE VCA',
      43 => 'Blue Pearl VTNE',
      44 => 'Rare Breed VTNE',
      'osu_np_6' => 'NAVLE OHIO',
    ];
    return !empty($mapping[$id]) ? $mapping[$id] : NULL;
  }

}
