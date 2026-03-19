<?php /** @noinspection ALL */

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\rest\ResourceResponse;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "student_results_resource",
 *   label = @Translation("Student results rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/student-results"
 *   }
 * )
 */
class StudentResultsResource extends ResourceBase {

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
    $QWGeneral = \Drupal::service('qwizard.general');
    $payload = [];

    $get_params_to_get = [
        'course' => 'course_id',
        'courseId' => 'course_id',
        'class' => 'class_id',
        'classId' => 'class_id',
        'type' => 'type',
        'uid' => 'uid',
        'user_id' => 'uid',
        'userId' => 'uid',
        'force_fresh_data' => 'force_fresh_data',
    ];
    $params = $rest_general_service->getInputsParams($get_params_to_get, [], $payload);

    if (!isset($params['uid'])) {
      $params['uid'] = \Drupal::currentUser()->id();
    }
    $uid = $params['uid'];

    $current_user = $QWGeneral->getAccountInterface('current');

    // Permission check. Only users with admin permission can see other's results. No check on development
    if ($current_user->id() != $params['uid']) {
      if (!($current_user->hasPermission('administer quiz results entities')
        || $QWGeneral->getCurrentEnv('string') == 'development')) {
        throw new AccessDeniedHttpException();
      }
    }
    if (!$current_user->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // Validation finished
    $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
    $results_service = \Drupal::service('qwizard.student_results_handler');
    $srStorage = \Drupal::entityTypeManager()->getStorage('qw_student_results');
    $course_handler = \Drupal::service('qwizard.coursehandler');


    $course_id = NULL;
    if (isset($params['course_id'])) {
      $course_id = $params['course_id'];
    }

    $class_id = NULL;
    if (isset($params['class_id'])) {
      $class_id = $params['class_id'];
    }

    if (empty($course_id) || $course_id == 'undefined') {
      $course = $course_handler->getCurrentCourse();
    } else {
      $termStorage = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term');
      $course = $termStorage->load($course_id);
    }

    // Load either their current user, or the one from parameters if given.
    // Permission check on viewing others results was already done above
    $student = $current_user;
    if($params['uid'] != $current_user->id()) {
      $student = $QWGeneral->getAccountInterface($params['uid']);
    }

    $subscription = $subscriptions_service->getCurrentSubscription($course, $uid);
    if (empty($subscription)) {
      throw new AccessDeniedHttpException();
    }

    // Can force fresh results to be generated through "force_fresh_data=1" query param, very slow though
    if (!empty($params['force_fresh_data']) &&
      ($QWGeneral->getCurrentEnv('number') >= 1 || $current_user->hasPermission('administer quiz results entities'))) {
      $results_service->rebuildStudentResults($student, $subscription);
    }


    // Get and load all results for this subscription
    $sResults = $results_service->getStudentResults($student, $subscription);
    $student_results = $srStorage->loadMultiple($sResults);

    // There's a bug somewhere that creates duplicates of pools. This dodges it
    $unique_check = [];
    foreach ($student_results as $key => $studentResult) {
      $name = $studentResult->getName();
      $valid = true;

      if (!empty($unique_check[$name])) {
        $valid = false;
      } else {
        $unique_check[$name] = 1;
      }

      if (!empty($uid) && $uid != $studentResult->getOwnerId()) {
        $valid = false;
      }
      if (!empty($course_id) && $course_id != $studentResult->getCourseId()) {
        $valid = false;
      }

      if (!empty($class_id) && $class_id != $studentResult->getClassId()) {
        $valid = false;
      }
      if (!empty($type) && $type != $studentResult->bundle()) {
        $valid = false;
      }

      if (!$valid) {
        unset($student_results[$key]);
      }
    }

    foreach ($student_results as $studentResult) {
      $payload['results_list'][$studentResult->id()] = [
        'srid' => $studentResult->id(),
        'name' => $studentResult->name->value,
        'subscription_id' => $studentResult->getSubscriptionId(),
        'course' => $studentResult->getCourseId(),
        'class' => $studentResult->getClassId(),
        'type' => $studentResult->bundle(),
        'updated' => $QWGeneral->formatIsoDate($studentResult->changed->value),
        'results' => $studentResult->getResultsJson('translated_array'),
      ];
    }

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }
}
