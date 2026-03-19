<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\qwizard\CourseHandler;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "classes_resource",
 *   label = @Translation("Classes resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/classes"
 *   }
 * )
 */
class ClassesResource extends ResourceBase {

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

    $rest_general_service = \Drupal::service('qwrest.general');
    $payload = [];

    $get_params_to_get = [
      'course' => 'course_id',
    ];
    $params = $rest_general_service->getInputsParams($get_params_to_get, [], $payload);

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    // @todo: This code could be simplified (refactored).
    $user_roles = $this->currentUser->getRoles();
    if (!empty($params['course_id'])) {
      $course_id = $params['course_id'];
      $class_ids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
        ->latestRevision()
        ->condition('vid', 'classes')
        ->condition('status', 1)
        ->condition('field_course', $course_id, '=')
        ->condition('field_role_access', $user_roles, 'IN')
        ->execute();
    }
    elseif ($current_course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse()) {
      $course_id = $current_course->id();
      $class_ids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
        ->latestRevision()
        ->condition('vid', 'classes')
        ->condition('status', 1)
        ->condition('field_course', $course_id, '=')
        ->condition('field_role_access', $user_roles, 'IN')
        ->execute();
    }
    else {
      $class_ids = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->getQuery()
        ->latestRevision()
        ->condition('vid', 'classes')
        ->execute();
    }
    $class_list = [];
    foreach ($class_ids as $class_id) {
      $class  = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($class_id);

      // Add image to response.
      $image_url = NULL;
      if ($class->hasField('field_image')) {
        $image_file = $class->field_image->entity;
        if (!empty($image_file)) {
          $image_url = $image_file->createFileUrl();
          $image_url = \Drupal::request()->getSchemeAndHttpHost() . $image_url;
        }
      }
      if (empty($image_url)) {
        $image_url = '/themes/custom/zukurenew/images/friendly-female-nurse.jpg';
        $image_url = \Drupal::request()->getSchemeAndHttpHost() . $image_url;
      }

      $class_list[] = [
        'tid' => $class_id,
        'uuid' => $class->uuid(),
        //'name' => t(_enforceBrandText($class->name->value)),
        'name' => t($class->name->value),
        'image' => $image_url,
      ];
    }
    $payload['class_list'] = $class_list;

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
