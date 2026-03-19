<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "marked_questions_toggle_resource",
 *   label = @Translation("Marked questions toggle resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/marked-questions-toggle"
 *   }
 * )
 */
class MarkedQuestionToggleResource extends ResourceBase {

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
  public function get()
  {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $rest_service = \Drupal::service('qwrest.general');
    $qw_general = \Drupal::service('qwizard.general');
    $course_handler = \Drupal::service('qwizard.coursehandler');

    $get_params_to_get = [
      'question_id',
      'active',
      'course',
    ];
    $input_params = $rest_service->getInputsParams($get_params_to_get);
    if(empty($input_params['active'])){
      $input_params['active'] = 0;
    }
    if(empty($input_params['course'])){
      $input_params['course'] = $course_handler->getCurrentCourse()->id();
    }

    $payload = ['status' => 0, 'message' => 'Failure', 'marked_question' => null];
    if(!empty($input_params['course']) && !empty($input_params['question_id']) && isset($input_params['active'])){

      $existing_marked_questions = $qw_general->getMarkedQuestions([
        'question_id' => $input_params['question_id'],
        'status' => 'all',
        'loaded' => true,
        'course' => $input_params['course'],
        'as_question_ids' => false
      ]);

      $marked_question = null;
      if(!empty($existing_marked_questions)){
        // MQ exists already. just set its status
        $marked_question = reset($existing_marked_questions);
        $marked_question->setStatus(!empty($input_params['active']));
        $payload['message'] = 'Updated';
      }else{
        // Create MQ, if it belongs in the current course
        $does_question_belong_in_course_pool = $qw_general->getTotalQuizzes([
          'course_id' => $input_params['course'],
          'ignore_access_check' => true,
          'question_ids' => [$input_params['question_id']],
          'count' => 1,
        ]);

        if(empty($does_question_belong_in_course_pool)){
          $payload['message'] = 'Question does not exist in current course';
        }else {
          $marked_question = \Drupal::entityTypeManager()->getStorage('marked_question')->create([
            'question' => $input_params['question_id'],
            'uid' => $this->currentUser->id(),
            'course' => $input_params['course'],
            'status' => $input_params['active']
          ]);
          $payload['message'] = 'Created';
        }
      }

      if(!empty($marked_question)) {
        $save_success = $marked_question->save();
        if ($save_success) {
          $payload['status'] = 1;
          $payload['marked_question'] = $marked_question->toArray();
        }
      }
    }

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }
}
