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
 *   id = "current_course_resource",
 *   label = @Translation("Current course resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/current-course"
 *   }
 * )
 */
class CurrentCourseResource extends ResourceBase {

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
    $payload = ['currentCourse' => [], 'availableCoursesForUser' => []];
    $get_params_to_get = ['course_id'];
    $rest_service = \Drupal::service('qwrest.general');
    $input = $rest_service->getInputsParams($get_params_to_get);

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // List out all courses the user has access to
    $courses = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('courses', 0, NULL, TRUE);
    foreach($courses as $course){
      $course_id = $course->id();
      $course_sub = \Drupal::service('qwsubs.subscription_handler')->getUserSubscriptions(null, $course, true);
      $payload['availableCoursesForUser'][$course_id] = null;

      if(!empty($course_sub)){
        $course_sub = reset($course_sub);
        $payload['availableCoursesForUser'][$course_id]  = [];
        $payload['availableCoursesForUser'][$course_id]['tid']  = (string) $course_id;
        $payload['availableCoursesForUser'][$course_id]['uuid']  = $course->uuid();
        $payload['availableCoursesForUser'][$course_id]['name'] = $course->label();
        $payload['availableCoursesForUser'][$course_id]['current'] = false;
        $payload['availableCoursesForUser'][$course_id]['premium'] = !empty($course_sub->getPremium());
      }
    }

    // Swap courses if course_id input is provided
    if(!empty($input['course_id']) && !empty($payload['availableCoursesForUser'][$input['course_id']])){
      $course_to_change_to = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($input['course_id']);
      \Drupal::service('qwizard.coursehandler')->setCurrentCourse($course_to_change_to);
    }

    $current_course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
    if (empty($current_course)) {
      $payload['message'] = 'No current course set';
      $response = new ResourceResponse($payload, 404);
      $response->setMaxAge(-1);
      return $response;
    }
    $payload["currentCourse"] = [
      'tid' => $current_course->id(),
      'uuid' => $current_course->uuid(),
      'name' => $current_course->name->value,
    ];
    $payload['availableCoursesForUser'][$current_course->id()]['current'] = true;

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Responds to PATCH requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function patch() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $rest_service = \Drupal::service('qwrest.general');
    $get_params_to_get = [];
    $payload = $rest_service->getInputsParams($get_params_to_get);

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    // @todo: Implement code to change current course.

    $response = new ModifiedResourceResponse($payload, 204);
    $response->setMaxAge(-1);
    return $response;
  }

}
