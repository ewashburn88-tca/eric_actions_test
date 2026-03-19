<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\Entity\QwPool;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwsubs\Entity\Subscription;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Drupal\zuku\ZukuGeneral;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "qw_membership_resource",
 *   label = @Translation("Quiz Wizard Membership & Subscriptions"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/membership"
 *   }
 * )
 */
class QwMembershipResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance              = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger      = $container->get('logger.factory')->get('qwrest');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $payload = [];

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if (!empty($_GET['user_id'])) {
      $uid = $_GET['user_id'];
    }
    elseif (!empty($payload['userId'])) {
      $uid = $payload['userId'];
    }
    if (!empty($_GET['course'])) {
      $course_id = $_GET['course'];
    }
    elseif (!empty($payload['courseId'])) {
      $course_id = $payload['courseId'];
    }

    $course_handler_service = \Drupal::service('qwizard.coursehandler');
    if (empty($uid)) {
      $uid = \Drupal::currentUser()->id();
      $course = $course_handler_service->getCurrentCourse();
    }
    if (empty($course_id)) {
      $course = $course_handler_service->getCurrentCourse();
    }
    else {
      $termStorage = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term');
      $course      = $termStorage->load($course_id);
    }

    if(!empty($course)) {
      $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
      $subscription = $subscriptions_service->getCurrentSubscription($course, $uid);
      if ($subscription instanceof Subscription) {
        $latest_term = $subscription->getLastSubTerm();
        $payload['subscription'] = [
          'Subscription_id' => $subscription->id(),
          'name' => $subscription->getName(),
          'status' => $subscription->isActive(),
          'subscription_created' => \Drupal::service('qwizard.general')->formatIsoDate($subscription->getCreatedTime()),
          'term_start' => $latest_term->getStart(),
          'expiration' => $latest_term->getEnd(),
          'should_show_flashcards' => $subscriptions_service->shouldShowFlashcards($course->id())
        ];

        // Add in the progress from the main classes.
        // @todo: This should be a setting or some how dynamic, for now it is tailored to zuku.
        $classes = ZukuGeneral::getPrimaryClassesForCourse($course->id());
        foreach ($classes as $mode => $class_id) {
          $pool = QwPool::getPoolForClass($class_id, $uid, $subscription->id());
          if (!empty($pool)) {
            $payload['progress'][$mode]['pool_id'] = $pool->id();
            $payload['progress'][$mode]['pool_name'] = $pool->getName();
            $payload['progress'][$mode]['pool_type'] = $pool->bundle();
            $payload['progress'][$mode]['total_questions'] = $pool->getQuestionCount();
            $payload['progress'][$mode]['questions_complete'] = $pool->getComplete();
          }
        }
      }else{
        $payload['error'] = 'Subscription not found';
      }
    }else{
      $payload['error'] = 'Subscription not found';
    }
    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
