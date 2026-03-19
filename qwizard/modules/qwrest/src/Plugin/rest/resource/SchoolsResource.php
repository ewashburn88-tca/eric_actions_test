<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "schools_resource",
 *   label = @Translation("Schools resource"),
 *   uri_paths = {
 *     "create" = "/api-v1/schools"
 *   }
 * )
 */
class SchoolsResource extends ResourceBase {

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
  public function get() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $payload = [];

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // @todo: query quizzes with filter is if given and order.
    if (!empty($_GET['region'])) {
      $qwiz_ids = \Drupal::entityTypeManager()->getStorage('qwiz')->getQuery()
        ->latestRevision()
        ->condition('class', $_GET['class'], '=')
        ->execute();
    }
    else {
      $qwiz_ids = \Drupal::entityTypeManager()->getStorage('qwiz')->getQuery()
        ->latestRevision()
        ->execute();
    }
    $qwiz_list = [];
    foreach ($qwiz_ids as $qwiz_id) {
      $qwiz  = \Drupal::entityTypeManager()
        ->getStorage('qwiz')
        ->load($qwiz_id);
      $qwiz_list[] = [
        'id' => $qwiz_id,
        'uuid' => $qwiz->uuid(),
        'name' => $qwiz->name->value,
        'class' => $qwiz->class->target_id,
        'topics' => $qwiz->topics->target_id,
      ];
    }
    $payload['qwiz_list'] = $qwiz_list;
    $response = new ModifiedResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
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
    $rest_service = \Drupal::service('qwrest.general');
    $get_params_to_get = [];
    $payload = $rest_service->getInputsParams($get_params_to_get);

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $response = new ModifiedResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
