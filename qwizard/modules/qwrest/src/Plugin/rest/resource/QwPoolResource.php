<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\Entity\QwPool;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "qw_pool_resource",
 *   label = @Translation("Quiz Wizard Pool Resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/qwpool"
 *   }
 * )
 */
class QwPoolResource extends ResourceBase {

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

        // You must to implement the logic of your REST Resource here.
        // Use current user after pass authentication to validate access.
        if (!$this->currentUser->hasPermission('access content')) {
            throw new AccessDeniedHttpException();
        }

      if (!empty($_GET['class'])) {
        $class_id = $_GET['class'];
      }
      elseif (!empty($payload['classId'])) {
        $class_id = $payload['classId'];
      }

      if (!empty($_GET['quiz'])) {
        $qwiz_id = $_GET['quiz'];
      }
      elseif (!empty($payload['quizId'])) {
        $qwiz_id = $payload['quizId'];
      }
      $uid = NULL;
      if (!empty($_GET['user_id'])) {
        $uid = $_GET['user_id'];
      }
      elseif (!empty($payload['userId'])) {
        $uid = $payload['userId'];
      }
      else {
        $uid = \Drupal::currentUser()->id();
      }
      $pool = QwPool::getPoolForClass($class_id, $uid);

      if (empty($pool)) {
        $payload['message'] = 'No pool found for this user '.$uid.' & class '.$class_id;
        return new ResourceResponse($payload, 404);
      }
      else {
        $payload['pool_id'] = $pool->id();
        $payload['pool_name'] = $pool->getName();
        $payload['pool_type'] = $pool->bundle();
        $payload['total_questions'] = $pool->getQuestionCount();
        $payload['questions_complete'] = $pool->getComplete();
        if (!empty($qwiz_id)) {
          $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
          $qwiz = $qwiz_storage->load($qwiz_id);
          $payload[$qwiz_id] = [
            'questions_in_pool' => $pool->getQuestionCountByQwiz($qwiz),
            'questions_complete' => $pool->getCompleteCountByQwiz($qwiz),
            'label' => $pool->label(),
          ];
        } else {
          $qwizzes = $pool->getQwizzesInPool(TRUE);
          foreach ($qwizzes as $qwiz) {
            $payload[$qwiz->id()] = [
              'questions_in_pool' => $pool->getQuestionCountByQwiz($qwiz),
              'questions_complete' => $pool->getCompleteCountByQwiz($qwiz),
              'label' => t($qwiz->label()),
            ];
          }
        }
      }

      $response = new ResourceResponse($payload, 200);
      $response->setMaxAge(-1);
      return $response;
    }

}
