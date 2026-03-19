<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\qwizard\ClassesHandler;
use Drupal\qwizard\Entity\QwizSnapshot;
use Drupal\qwizard\QwizardGeneral;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "marked_questions_resource",
 *   label = @Translation("Marked questions resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/marked-questions"
 *   }
 * )
 */
class MarkedQuestionsResource extends ResourceBase {

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
    $payload = [];
    $course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse(); //aziz
    // Get request params
    $get_params_to_get = [
      'quiz_id',
      'quizId' => 'quizId',
      'type',
      'class',
      'quiz_table',
      'quizTable' => 'quiz_table',
      'course_id'
    ];
    $rest_general_service = \Drupal::service('qwrest.general');
    $input_params = $rest_general_service->getInputsParams($get_params_to_get, [], []);
    if(empty($input_params['course_id'])){
      $input_params['course_id'] = $course->id();
    }
    $current_lang = \Drupal::languageManager()->getCurrentLanguage()->getId();


    // Get user test settings for the list of marked q's.
    $marked_questions = QwizardGeneral::getMarkedQuestions(['loaded' => true]);
    //$questions = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($questions_to_load);

      if (!empty($marked_questions)) {
      $question_types = QwizardGeneral::getListOfQuestionTypes(TRUE);

      // Allow for type to be filtered ?type=qw_flashcard || ?$type=qw_simple_choice
      if(!empty($input_params['type'])){
        foreach($question_types as $key=>$value){
          if($input_params['type'] != $value){
            unset($question_types[$key]);
          }
        }
      }

      $node_storage   = \Drupal::entityTypeManager()->getStorage('node');
      $query          = \Drupal::entityQuery('node')
        ->condition('type', $question_types, 'IN')
        ->condition('status', 1)
        ->condition('nid', $marked_questions, 'IN')
        ->condition('field_courses', $course->id(), '='); //aziz
      if (!empty($input_params['quiz_id'])) {
        $qwiz_id        = $input_params['quiz_id'];
        $qwiz_storage   = \Drupal::entityTypeManager()->getStorage('qwiz');
        $qwiz           = $qwiz_storage->load($qwiz_id);
        $qwiz_questions = $qwiz->getQuestionIds();
        $query->condition('nid', $qwiz_questions, "IN");
      }
      elseif (!empty($input_params['class'])) {
        $class_id = $input_params['class'];
        $query->condition('field_classes', $class_id);
        if (!empty($input_params['quiz_table'])) {
          // We are going to return a list of qwizzes in class with totals.
          $qwiz_list = ClassesHandler::getQwizzesInClass($class_id, TRUE);
          $total = 0;
          foreach ($qwiz_list as $qwiz) {
            // @todo: Exclude random from count. Fragile, find better way.
            if ($qwiz->label() == 'Random') continue;
            $qwiz_query = clone $query;
            $qwiz_questions = $qwiz->getQuestionIds();
            $qwiz_qnids = array();
            if(count($qwiz_questions)) {
              $qwiz_query->condition('nid', $qwiz_questions, "IN");
              $qwiz_qnids = $qwiz_query->execute();
            }

            $payload['qwiz_list'][$qwiz->id()] = [
              'Topic' => t($qwiz->label()),
              'Count' => count($qwiz_qnids),
            ];
            $total += count($qwiz_qnids);
          }
          // Total.
          $payload['qwiz_list']['total'] = [
            'Topic' => t('Total'),
            'Count' => $total,
          ];

          $response = new ResourceResponse($payload, 200);
          $response->setMaxAge(-1);
          return $response;
        }
      }
      $nids = $query->execute();

      // Get questions in snapshot format.
      foreach ($nids as $qid) {
        $question                   = QwizSnapshot::buildSnapshotQuestionsArray([$qid], TRUE);
        $payload['questions'][$qid] = $question;
      }
    }
    else {
      $payload['message'] = t('You haven\'t marked any questions yet.');
    }

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

}
