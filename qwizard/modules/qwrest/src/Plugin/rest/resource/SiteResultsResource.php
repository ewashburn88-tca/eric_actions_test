<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\qwizard\QwizardGeneral;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "site_results_resource",
 *   label = @Translation("Site results"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/site-results"
 *   }
 * )
 */
class SiteResultsResource extends ResourceBase {

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
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('qwrest');
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

      // Use current user after pass authentication to validate access.
      if (!$this->currentUser->hasPermission('access content')) {
          throw new AccessDeniedHttpException();
      }
      $QParams = \Drupal::request()->query;

      //use only one course if specified
      $courses = \Drupal::service('qwizard.coursehandler')->getActiveCourses();
      $QWGeneral = \Drupal::service('qwizard.general');
      $rest_service = \Drupal::service('qwrest.general');
      $course_id = null;
      if (!empty($QParams->get('course'))) {
        $course_name = strtoupper($QParams->get('course'));
        $course_id = array_search($course_name, $courses);
      }
      //use only one course by ID if specified
      elseif (!empty($QParams->get('courseId'))) {
        $course_id = $QParams->get('courseId');
      }

      //allow developers to bypass cached quiz data
      $force_fresh_data = false;
      if(!empty($QParams->get('force_fresh_data')) && ($this->currentUser->hasPermission('administer quiz results entities')
          || $QWGeneral->getCurrentEnv('string') == 'development')){
        $force_fresh_data = true;
      }

      $payload = $rest_service->getSiteResultsData($course_id, $force_fresh_data);

      $response = new ResourceResponse($payload, 200);
      $response->setMaxAge(-1);
      return $response;
    }
}
