<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\qwizard\Entity\QwizSnapshot;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "question_resource",
 *   label = @Translation("Question resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/question"
 *   }
 * )
 */
class QuestionResource extends ResourceBase {

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

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    if (!empty($_GET['question_id'])) {
      $qid = $_GET['question_id'];
    }
    elseif (!empty($payload['questionId'])) {
      $qid = $payload['questionId'];
    }
    if (!empty($qid)) {
      // Return the question in the snapshot format.
      $payload['question'] = QwizSnapshot::buildSnapshotQuestionsArray([$qid]);
    }

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
