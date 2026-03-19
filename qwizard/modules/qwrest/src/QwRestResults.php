<?php

namespace Drupal\qwrest;


use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\qwizard\MergedQwiz;
use Drupal\rest\ResourceResponse;

/**
 * Class QwRestGeneral.
 */
class QwRestResults {

  /**
   * Constructs a new QwRestGeneral object.
   */
  public function __construct() {
    //@todo DI
  }

  /**
   * /api-v1/qwiz-result
   * Returns a list of users results from individual sessions
   * Broken out into its own function so PHP can call this API as well
   * @param $user
   * @param $subscription
   * @param null $qwiz_id - To load a single result by ID
   * @param null $result_id - Can be used to pass in already loaded results
   * @param array $results
   * @return array|ResourceResponse
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getResultData($user, $subscription, $qwiz_ids = null, $result_id = null, $param_results = [], $only_published_pools = true){
    $rest_service = \Drupal::service('qwrest.general');
    $merged_quiz_service = \Drupal::service('qwizard.merged_qwiz');
    $merged_quiz_service->setUser($user);
    $payload = [];
    // Load and return a quiz session result.
    if (!empty($result_id)) {
      $payload = $rest_service->getSessionArray($result_id, ['published_pools_only' => $only_published_pools, 'include_snapshot' => false, 'update_reviewed_time' => false]);
      if (empty($payload)) {
        // @todo this payload response may not work here in the service
        $payload = 'This result wasn\'t found';
        $response = new ResourceResponse($payload, 404);
        $response->setMaxAge(-1);
        return $response;
      }

    } else {
      $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
      $qwiz_results = [];
      $result_qwizzes = [];

      $qwiz_results = $param_results;
      if(empty($param_results)) {
          $qwiz_results = $this->getResultEntities($user, $subscription, $qwiz_ids);
      }

      // Loop through it first to get entities to pre-load
      $qwizResultQuizzes_to_load = [];
      if(!empty($qwiz_results)) {
        $sessions = $rest_service->getMultipleSessionArrays($qwiz_results, $user->id(), $subscription->id(), ['include_snapshot' => false, 'update_reviewed_time' => false, 'minimal' => true]);
        foreach ($qwiz_results as $result) {
          $result_id = $result->id();
          $payload['results_list'][$result_id] = $sessions[$result_id];
          $qwiz_result_qwiz_id = $payload['results_list'][$result_id]['qwiz_id'];
          $qwizResultQuizzes_to_load[$qwiz_result_qwiz_id] = $qwiz_result_qwiz_id;
        }
        /*foreach ($qwiz_results as $result) {
          $result_id = $result->id();
          // @todo getMultiple - would require a new function besides getSessionArray, which is already used elsewhere
          $payload['results_list'][$result_id] = $rest_service->getSessionArray($result, ['published_pools_only' => $only_published_pools, 'include_snapshot' => false, 'update_reviewed_time' => false, 'minimal' => true]);
          $qwiz_result_qwiz_id = $payload['results_list'][$result_id]['qwiz_id'];
          $qwizResultQuizzes_to_load[$qwiz_result_qwiz_id] = $qwiz_result_qwiz_id;
        }*/
      }
      $qwizResultQuizzes = $qwiz_storage->loadMultiple($qwizResultQuizzes_to_load);


      foreach ($qwiz_results as $result) {
        $qwiz_result_qwiz_id = $payload['results_list'][$result_id]['qwiz_id'];
        $qwizResultQuiz = $qwizResultQuizzes[$qwiz_result_qwiz_id];
        $result_qwizzes[$qwiz_result_qwiz_id] = $qwizResultQuiz;
      }

      // See if there are any results shared with merged quizzes.
      // @todo why does this only bother with the last one?
      if (!empty($qwizResultQuiz)) {
        $merged_quizzes = $merged_quiz_service->mergedQuizzesWithSharedTopic($qwizResultQuiz);
        foreach ($merged_quizzes as $merged_quiz) {
          $merged_quiz_service->setQwiz($merged_quiz);
          // @todo getMultiple
          $payload['merged_quiz_results'][$merged_quiz->getName()] = $merged_quiz_service->getIndividualResults($result_qwizzes, $subscription->id());
        }
      }

    }

    // Post-processing
    if(!empty($payload)){
      // remove empty 0/0 items. They will still exist in DB but will not be rendered
      // @todo move this to base query
      foreach($payload['results_list'] as $key=>$value){
        if(isset($value['correct']) && isset($value['attempted'])){
          if($value['correct'] == 0 && $value['attempted'] == 0){
            unset($payload['results_list'][$key]);
          }
        }
      }

      // React does not handle empty "merged_quiz_results" well, unset it here if empty
      if(!empty($payload['merged_quiz_results'])) {
        foreach ($payload['merged_quiz_results'] as $key => $value) {
          if (empty($value)) {
            unset($payload['merged_quiz_results'][$key]);
          }
        }
      }
      if(empty($payload['merged_quiz_results'])){
        unset($payload['merged_quiz_results']);
      }
    }

    #if(!empty($payload)) dpm($payload);
    return $payload;
  }

  /**
   * High performance query for getting a users qwiz_result's attached to a subscription
   *
   * @param $user
   * @param $subscription
   * @param array $qwiz_ids
   * @param array $class_ids
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getResultEntities($user, $subscription, $qwiz_ids = [], $class_ids = []): array
  {
    // $query_type Can be used to swap back to entity query method if  is fixed
    $qwiz_result_storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $con = Database::getConnection('default', 'default');
    $query = $con->select('qwiz_result', 'qr');
    $query->fields('qr', ['id', 'created']);
    $query->condition('qr.user_id', $user->id());
    $query->condition('qr.subscription_id', $subscription->id());

    if(!empty($class_ids)){
      if(!is_array($class_ids) || count($class_ids) == 1){
        $query->condition('qr.class', $class_ids, '=');
      }else{
        $query->condition('qr.class', $class_ids, 'IN');
      }
    }

    if (!empty($qwiz_ids)) {
      if(!is_array($qwiz_ids) || count($qwiz_ids) == 1){
        $query->condition('qr.qwiz_id', $qwiz_ids, '=');
      }else{
        $query->condition('qr.qwiz_id', $qwiz_ids, 'IN');
      }
    }

    $query_data = $query->execute()->fetchAll(\PDO::FETCH_OBJ);

    // Sorting in PHP since table is too big for fast sorts
    uasort($query_data, function ($a, $b) {
      return ($a->created < $b->created);
    });

    $qwiz_result_ids = [];
    foreach ($query_data as $data) {
      $qwiz_result_ids[] = $data->id;
    }

    $qwiz_results = $qwiz_result_storage->loadMultiple($qwiz_result_ids);

    return $qwiz_results;
  }
}
