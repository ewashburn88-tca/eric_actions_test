<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\Core\Session\AccountInterface;
use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\qwizard\MergedQwiz;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use \Drupal\qwizard\QwizResultInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "qwiz_results_resource",
 *   label = @Translation("Qwiz result rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/qwiz-result"
 *   }
 * )
 */
class QwizResultsResource extends ResourceBase {

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
   * Responds to GET requests for a single quiz session result.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get()
  {
    $payload = [];
    \Drupal::service('page_cache_kill_switch')->trigger();
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    // Handle single result request.
    $result_id = null;
    if (!empty($_GET['result_id'])) {
      $result_id = $_GET['result_id'];
    } elseif (!empty($payload['resultId'])) {
      $result_id = $payload['resultId'];
    }

    $qwiz_id = null;
    if(!empty($_GET['quizId'])){
      $qwiz_id = $_GET['quizId'];
    }
    if(!empty($_GET['quiz_id'])){
      $qwiz_id = $_GET['quiz_id'];
    }

    // Due to a bug in the app as of 10-4-2022, and to be fixed in an update, param is sent as quiz_id2 instead of quiz_id=2
    // This bit of logic is meant to grab the quiz_id regardless
    if(empty($qwiz_id)){
      foreach($_GET as $key=>$value){
        if(empty($value) && str_contains($key, 'quiz_id')){
          $qwiz_id = (int) str_replace('quiz_id', '', $key);
        }
      }
    }

    $user = $this->currentUser;
    $course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
    $subscription =  \Drupal::service('qwsubs.subscription_handler')->getCurrentSubscription($course);
    $rest_service = \Drupal::service('qwrest.results');

    $payload = $rest_service->getResultData($user, $subscription, $qwiz_id, $result_id);

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Responds to PATCH requests for a single quiz session result.
   *
   * @param string $payload
   *
   * @return ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function patch()
  {
    $payload = [];
    \Drupal::service('page_cache_kill_switch')->trigger();

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $rest_service = \Drupal::service('qwrest.general');
    $get_params_to_get = [
      'end',
      'qwiz_id'
    ];
    $input = $rest_service->getInputsParams($get_params_to_get);
    $qwiz_id = $input['qwiz_id'];
    $end = $input['end'];



    if(empty($qwiz_id) || !intval($qwiz_id)) {
      $msg      = t("You must provide a qwiz id");
      $response = new ModifiedResourceResponse($msg, 400);
      $response->setMaxAge(-1);
      return $response;
    }

    if($end === 'now') {
      // check if already assigned, skip if it is
      $query = \Drupal::database()->select('qwiz_result', 'q');
      $query->fields('q', ['id']);
      $query->condition('user_id', $this->currentUser->id());
      $query->condition('qwiz_id', $qwiz_id);
      $query->condition('changed', strtotime('now') - 600, '<');
      $query->isNull('end');
      $existing_items = $query->execute()->fetchAll();
      $existing_items_ids = [];
      foreach($existing_items as $item){
        $existing_items_ids[$item->id] = $item->id;
        \Drupal::logger('QwizSession')->notice('Test ended from /api-v1/qwiz-session: ' . $item->id);
      }

      // Mark end data on abandoned tests
      if(!empty($existing_items)) {
        $qwiz_result_storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
        $existing_qwiz_results = $qwiz_result_storage->loadMultiple($existing_items_ids);
        foreach($existing_qwiz_results as $existing_qwiz_result){
          $existing_qwiz_result->endQwizResult(false);
        }
        \Drupal::service('qwizard.general')->rebuildResultsForUser($this->currentUser->id(), true);
/*

        $query = \Drupal::database()->update('qwiz_result');
        $query->fields(['end' => date("c", time())]);
        $query->condition('id', $existing_items_ids, 'IN');
        $query->execute();
        \Drupal::entityTypeManager()->getStorage('qwiz_result')->resetCache($existing_items_ids);*/
      }
    }

    $response = new ModifiedResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
