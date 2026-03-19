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
 *   id = "qwiz_resource",
 *   label = @Translation("Qwiz resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/qwiz"
 *   }
 * )
 */
class QwizResource extends ResourceBase {

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
    $payload = [];
    \Drupal::service('page_cache_kill_switch')->trigger();

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    // @todo: query quizzes with filter is if given and order.
    if (!empty($_GET['class'] || !empty($payload['class']))) {
      $class_id = empty($_GET['class']) ? $payload['class'] : $_GET['class'];
      $qwiz_ids = \Drupal::entityTypeManager()->getStorage('qwiz')->getQuery()
        ->latestRevision()
        ->condition('class', $class_id, '=')
        ->condition('status', 1)
        ->sort('name', 'ASC')
        ->execute();
    }
    else {
      $qwiz_ids = \Drupal::entityTypeManager()->getStorage('qwiz')->getQuery()
        ->latestRevision()
        ->execute();
    }
    $qwiz_list = [];
    $qwizzes = \Drupal::entityTypeManager()->getStorage('qwiz')->loadMultiple($qwiz_ids);
    $tags_to_load = [];
    $qwiz_tags = [];
    foreach($qwizzes as $qwiz){
      // @todo this should support multiple topics. A few other areas are guilty of this as well
      if(!empty($qwiz->topics->target_id)) {
        $topic_id = $qwiz->topics->target_id;
        $tags_to_load[$topic_id] = $topic_id;
      }

      if(!empty($qwiz->class->target_id)) {
        $class_id = $qwiz->class->target_id;
        $tags_to_load[$class_id] = $class_id;
      }
    }
    if(!empty($tags_to_load)) {
      $qwiz_tags = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tags_to_load);
    }

    foreach ($qwizzes as $qwiz) {
      // Don't count qwiz's with unpublished topic or class
      if((!empty($qwiz_tags[$qwiz->topics->target_id]) && !$qwiz_tags[$qwiz->topics->target_id]->isPublished()) ||
         (!empty($qwiz_tags[$qwiz->class->target_id]) && !$qwiz_tags[$qwiz->class->target_id]->isPublished())){
        continue;
      }

      $image_file = $qwiz->field_qwiz_image->entity;
      if (!empty($image_file)) {
        $image_url = $image_file->createFileUrl();
        $image_url = \Drupal::request()->getSchemeAndHttpHost() . $image_url;
      }
      else {
        $image_url = '/themes/custom/zukurenew/images/friendly-female-nurse.jpg';
        $image_url = \Drupal::request()->getSchemeAndHttpHost() . $image_url;
      }
      $qwiz_list[] = [
        'id' => $qwiz->id(),
        'uuid' => $qwiz->uuid(),
        'name' => t(_enforceBrandText($qwiz->name->value)),
        'class' => $qwiz->class->target_id,
        // @todo topics should have multiple values. All are single for this API at present, would need to adjust react to change
        'topics' => $qwiz->topics->target_id,
        'image' => $image_url,
        'weight' => $qwiz->field_weight->value,
      ];
    }
    $payload['qwiz_list'] = $qwiz_list;

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
