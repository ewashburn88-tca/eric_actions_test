<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Dompdf\Exception;
use Drupal\Component\Serialization\Json;
// use Drupal\qwizard\CourseHandler;
// use Drupal\qwizard\QwizardGeneral;
// use Drupal\qwsubs\Entity\Subscription;
// use Drupal\qwsubs\SubscriptionHandler;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\zuku\ZukuGeneral;
use Drupal\user\Entity\User;


/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "tester_support_resource",
 *   label = @Translation("Tester support resource"),
 *   uri_paths = {
 *     "create" = "/api-v1/tester-support"
 *   }
 * )
 */
class TesterSupportResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  // protected $userStorage;

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
   * Responds to POST requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $rest_contact_service = \Drupal::service('qwrest.contact');
    $payload_response = $rest_contact_service->submitToTesterSupport();

    $response = new ModifiedResourceResponse($payload_response, 200);
    $response->setMaxAge(-1);
    return $response;
  }
}

