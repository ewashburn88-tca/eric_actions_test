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
 *   id = "courses_resource",
 *   label = @Translation("Courses resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/courses"
 *   }
 * )
 */
class CoursesResource extends ResourceBase {

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

    $course_ids = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->getQuery()
      ->latestRevision()
      ->condition('vid', 'courses')
      ->execute();

    $course_list = [];
    foreach ($course_ids as $course_id) {
      $course        = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($course_id);
      $course_list[] = [
        'tid'  => $course_id,
        'uuid' => $course->uuid(),
        'name' => $course->name->value,
      ];
    }
    $payload['course_list'] = $course_list;

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
