<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\qwizard\Entity\Qwiz;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\qwizard\QwizSessionHandler;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use \Drupal\qwizard\Controller\QwizController;
use \Drupal\qwizard\QwizInterface;
use \Drupal\qwizard\QwizResultInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "qwiz_session_resource",
 *   label = @Translation("Qwiz session rest resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/qwiz-session",
 *     "create" = "/api-v1/qwiz-session"
 *   }
 * )
 */
class QwizSessionResource extends ResourceBase {

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
   * Loads a test session for review.
   *
   * @param string $payload
   *
   * @return ModifiedResourceResponse|ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $rest_service = \Drupal::service('qwrest.general');
    $payload = [];

    // If debug print payload to log.
    if (qwizard_in_debug_mode()) {
      $prefix_text = 'Get Payload rcvd:';
      $this->log_debug($payload, $prefix_text);
    }

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    if (!empty($_GET['result_id'])) {
      $result_id = $_GET['result_id'];
    }
    elseif (!empty($payload['resultId'])) {
      $result_id = $payload['resultId'];
    }
    else {
      $msg      = t("You must provide a result id, either as query parameter result_id or body parameter resultId.");
      $response = new ModifiedResourceResponse($payload, 400);
      $response->setMaxAge(-1);
      return $response;
    }
    // Load and return a quiz session result.
    $payload = $rest_service->getSessionArray($result_id, ['include_snapshot' => true, 'update_reviewed_time' => true]);
    if (empty($payload)) {
      $payload  = 'This result wasn\'t found';
      $response = new ResourceResponse($payload, 404);
      $response->setMaxAge(-1);
      return $response;
    }

    // If debug print payload to log.
    if (qwizard_in_debug_mode()) {
      $prefix_text = 'Get Payload sent:';
      $this->log_debug($payload, $prefix_text);
    }

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Responds to POST requests.
   *
   * Starts a test session.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post() {
    $rest_general_service = \Drupal::service('qwrest.general');

    $get_params_to_get = [
      'length',
      'count',
      'altType',
      'quizId',
      'marked',
      'session',

    ];
    $payload = $rest_general_service->getInputsParams($get_params_to_get);

    // Currently, app sends msq, which is deprecated. Force mmq here
    if(!empty($payload['altType']) && $payload['altType'] == 'msq'){
      $payload['altType'] = 'mmq';
    }


    \Drupal::service('page_cache_kill_switch')->trigger();
    $rest_service = \Drupal::service('qwrest.general');

    // If debug print payload to log.
    if (qwizard_in_debug_mode()) {
      $prefix_text = 'Post Payload rcvd:';
      $this->log_debug($payload, $prefix_text);
    }

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    // Check for count.
    if (!empty($payload['count']) || !empty($payload['length'])) {
      $length = empty($payload['count']) ? $payload['length'] : $payload['count'];
    }
    else {
      $length = 60;
    }
    // Check if alternate quiz.
    if (isset($payload['altType'])) {
      $alt_type = $payload['altType'];
    }
    else {
      $alt_type = 'standard';
    }
    // Load the quiz.
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('qwiz')
      ->load($payload['quizId']);

    if (!($quiz instanceof Qwiz)) {
      $payload['error'] = 'Creating session failed.';
      $params = ['@alt' => $alt_type, '@quiz' => $payload['quizId']];
      \Drupal::logger('QwizSession')
        ->error('Creating session failed for @alt quiz @quiz: quiz not type qwiz or empty', $params);
    }

    $onlyMarked = !empty($payload['marked']) && $payload['marked'] === true;
    try {
      $qwiz_session = QwizSessionHandler::initializeQuiz($quiz, $length, $alt_type, $onlyMarked, $payload);
      $payload['session'] = $rest_service->getSessionArray($qwiz_session);
    }catch(\Exception $e){
      \Drupal::logger('QwizSessionResource')->error('Unable to initialize quiz on qwiz-session post - payload input was '.json_encode($payload));
    }

    if(empty($qwiz_session)){
      $payload['error'] = 'Creating session failed.';
      $params = ['@alt' => $alt_type, '@quiz' => $quiz->label()];
      \Drupal::logger('QwizSession')
        ->error('Creating session failed for @alt quiz @quiz', $params);
    }

    // If debug print payload to log.
    if (qwizard_in_debug_mode()) {
      $prefix_text = 'Post Payload sent:';
      $this->log_debug($payload, $prefix_text);
    }
    $response = new ModifiedResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Responds to PATCH requests.
   *
   * Modifies a test session (change question & record results).
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

    $get_params_to_get = [
      'resultId',
      'session',
      'end',
      'altType',
    ];
    $payload = $rest_service->getInputsParams($get_params_to_get);

    // Currently, app sends msq, which is deprecated. Force mmq here
    if(!empty($payload['altType']) && $payload['altType'] == 'msq'){
      $payload['altType'] = 'mmq';
    }

    // If debug print payload to log.
    if (qwizard_in_debug_mode()) {
      $prefix_text = 'Patch Payload rcvd:';
      $this->log_debug($payload, $prefix_text);
    }

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    if (empty($payload['resultId'])) {
      $response = new ModifiedResourceResponse("The resultId parameter is required.", 400);
      $response->setMaxAge(-1);
      return $response;
    }

    $qwiz_result_storage = \Drupal::entityTypeManager()
      ->getStorage('qwiz_result');
    $qwiz_result         = $qwiz_result_storage->load($payload['resultId']);

    if (empty($qwiz_result)) {
      $payload['msg'] = "The quiz result could not be found.";
      $response       = new ModifiedResourceResponse($payload, 404);
      $response->setMaxAge(-1);
      return $response;
    }
    if (!empty($payload['altType'])
      && $payload['altType'] == 'marked'
      && !empty($payload['end'])) {
      // Remove empty results.
      if (empty($qwiz_result->getAttempted())) {
        if ($qwiz_result->getEndTime() == $qwiz_result->start->value) {
          // This is a marked review, so delete the result.
          // @todo: removing marked qwizResult should be handled better.
          $qwiz_result->delete();
        }
      }
      $payload['msg'] = "Quiz result was removed.";
      $response       = new ModifiedResourceResponse($payload, 200);
      $response->setMaxAge(-1);
      return $response;
    }
    if (!empty($qwiz_result->getEndTime())) {
      $payload  = ['message' => 'This quiz session has been closed.'];
      $response = new ModifiedResourceResponse($payload, 406);
      $response->setMaxAge(-1);

      // If debug print payload to log.
      if (qwizard_in_debug_mode()) {
        $prefix_text = 'This quiz session has been closed. QResult: ' . $qwiz_result;
        $this->log_debug($payload, $prefix_text);
      }

      return $response;
    }
    // Handle test navigation and scoring.
    // Record new session data.
    $session_data = $payload['session'];
    $rest_service->setSessionArray($session_data, $qwiz_result, !empty($payload['end']));
    $payload['session'] = $rest_service->getSessionArray($payload['resultId']);
    // If debug print payload to log.
    if (qwizard_in_debug_mode()) {
      $prefix_text = 'Patch Payload sent:';
      $this->log_debug($payload, $prefix_text);
    }

    $response = new ModifiedResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Logs debug info.
   *
   * @param $output
   * @param $prefix_text
   */
  public function log_debug($output, $prefix_text) {
    if (!empty($output['session']['snapshot']['questions'])) {
      foreach ($output['session']['snapshot']['questions'] as &$question) {
        unset($question['question_text']);
        unset($question['feedback']);
        unset($question['answers']);
      }
    }
    $prefix_text = $current_path = \Drupal::service('path.current')
        ->getPath() . '<br>' . $prefix_text;
    $json        = json_encode($output, JSON_PRETTY_PRINT);
    \Drupal::logger('QwizSessionResource Debug')
      ->debug($prefix_text . '<br><pre>' . $json . '</pre>');
  }

}
