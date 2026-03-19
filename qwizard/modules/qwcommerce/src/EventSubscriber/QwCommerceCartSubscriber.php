<?php

namespace Drupal\qwcommerce\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\MembershipHandler;
use Drupal\qwizard\QwizardGeneral;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Handles events with cart events.
 */
class QwCommerceCartSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var MembershipHandler
   */
  protected $membershipHandler;

  /**
   * Constructs a new OrderSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, MembershipHandler $membershipHandler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser       = $current_user;
    $this->membershipHandler = $membershipHandler;
  }

  /**
   * @param ContainerInterface $container
   * @return OrderSubscriber
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container): QwCommerceCartSubscriber
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('qwizard.membership')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CartEvents::CART_ENTITY_ADD  => ['onCartChange', 1000],
      CartEvents::CART_ORDER_ITEM_UPDATE  => ['onCartChange', 1000],
      CartEvents::CART_ORDER_ITEM_REMOVE  => ['onCartChange', 1000],
      KernelEvents::RESPONSE => ['checkRedirectIssued', -10],
    ];
  }

  /**
   * Activates the subscriptions on order paid.
   *
   * @param $event
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onCartChange($event) {
    $cart = $event->getCart();
    $order_items = $cart->getItems();

    // Force at one each
    foreach($order_items as $order_item){
      $order_item->setQuantity('1');
      $order_item->save();
    }

    // Make sure that only one item per course is added
    $order_items_by_class = [];
    foreach(array_reverse($order_items) as $order_item){
      $product_variation = $order_item->getPurchasedEntity();
      $product = $product_variation->getProduct();
      #$type = $product->bundle();

      if(!empty($product->field_course)) {
        $course = $product->field_course->entity;
        $course_id = $course->id();

        if (empty($order_items_by_class[$course_id])) {
          $order_items_by_class[$course_id] = $order_item;
        } else {
          // User added a product from this course earlier. Unset it
          $order_item->delete();
        }
      }
    }


    $current_path = explode('?', \Drupal::request()->getRequestUri())[0];
    $path_args = explode('/', $current_path);
    if($path_args[1] != 'cart') {
      // Use this if you want to just redirect to checkout on cart change. Uncomment KernelEvents::RESPONSE in getSubscribedEvents too to enable
      \Drupal::requestStack()->getCurrentRequest()->attributes->set('_checkout_redirect_url', Url::fromRoute('commerce_checkout.form', [
        'commerce_order' => $event->getCart()->id(),
      ])->toString());
    }
  }

  /**
   * Checks if a redirect rules action was executed.
   *
   * Redirects to the provided url if there is one.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The response event.
   */
  public function checkRedirectIssued(FilterResponseEvent $event) {
    $request = $event->getRequest();
    $redirect_url = $request->attributes->get('_checkout_redirect_url');
    if (isset($redirect_url)) {
      $event->setResponse(new RedirectResponse($redirect_url));
    }
  }

}
