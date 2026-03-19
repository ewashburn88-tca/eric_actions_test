<?php
/**
 * @file
 */

namespace Drupal\qwflashcards\Plugin\QueueWorker;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\zuku\ZukuGeneral;

/**
 * Processes update user import tasks for Zuku import module.
 *
 * @QueueWorker(
 *   id = "update_marked_questions_queue_worker",
 *   title = @Translation("update_marked_questions_queue_worker: Queue worker"),
 *   cron = {"time" = 55}
 * )
 */
class UpdateMarkedQuestionsQueueWorker extends QueueWorkerBase {
  protected $profile = null;
  protected $loop_counter_for_json = 0;

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    set_time_limit(600);

    $profile_id = $data;
    //$delete = !empty($data->delete);
    // Create user, subscriptions and record test data.
    $success = $this->updateMarkedQuestionsOnProfile($profile_id);

    // Log info.
    if ($success) {
      // Remove from state
      \Drupal::state()->delete('update_marked_questions_queue_worker-' . $profile_id);

    }
    else {
      \Drupal::logger('questions_tags_to_paragraphs_queue')->info("Failure on ".$profile_id);
    }

    return $success;
  }
  private function updateMarkedQuestionsOnProfile($profile_id)
  {
    // There's no great way of determining pass/fail on this
    $success = 1;

    $profile =   \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->load($profile_id);
    $this->profile = $profile;

    $marked_cards = $this->handleJSON($profile->field_marked_cards->value);
    $marked_questions = $this->handleJSON($profile->field_marked_questions->value);

    // if(is_array($marked) && count($marked)) {
    //   $marked_cards = [];
    //   $marked_questions = [];
    //   foreach($marked as $marked_question_id) {
    //     if(!empty($marked_question_id)) {
    //       $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    //       $marked_question_node = $node_storage->load($marked_question_id);

    //       if($marked_question_node) {
    //         $is_flashcard = $marked_question_node->bundle() === 'qw_flashcard';
    //         if($is_flashcard) {
    //           $marked_cards[] = $marked_question_id;
    //         } else {
    //           $marked_questions[] = $marked_question_id;
    //         }
    //       }
    //     }
    //   }

    //   $profile->field_marked_cards->value = count($marked_cards) ? Json::encode(array_unique($marked_cards)) : NULL;
    //   $profile->field_marked_questions->value = count($marked_questions) ? Json::encode(array_unique($marked_questions)) : NULL;
    //   $profile->save();
    // }

    /*var_dump($marked_cards);
    var_dump($marked_questions);
    exit;*/

    if(!empty($marked_cards)) {
      foreach ($marked_cards as $question_id) {
        $this->assignCardToUser($question_id);
      }
    }

    if(!empty($marked_questions)) {
      foreach ($marked_questions as $question_id) {
        $this->assignCardToUser($question_id);
      }
    }

    return $success;
  }

  private function handleJSON($json){
    if(str_contains($json, 'u0022')){
      var_dump($json); exit;
    }

    $return = json_decode($json, true);

    // Handles an edge case where the above returns a JSON string instead
    if(is_string($return)){
      $this->loop_counter_for_json++;

      // If this is hit, find a better way to handle this
      if($this->loop_counter_for_json > 10){
        var_dump($json); exit;
      }


      $return = $this->handleJSON($return);
    }

    // clean up the json, remove null values and duplicate values
    if(is_array($return)){
      foreach($return as $key=>$value){
        if(empty($value)){
          unset($return[$key]);
        }
      }

      $return = array_unique($return);
    }

    return $return;
  }

  private function assignCardToUser($marked_id){
    $marked_id = (string) $marked_id;
    $user_id = $this->profile->get('uid')->getValue()[0]['target_id'];

    // Required fields
    if(empty($user_id) || empty($marked_id)){
      return;
    }

    $course_id = \Drupal::service('qwizard.coursehandler')->getCurrentCourse(); //aziz
    $properties = [
      'question' => $marked_id,
      'uid'      => $user_id,
      'course'   => $course_id
    ];


    // check if already assigned, skip if it is
    $existing_items = \Drupal::entityTypeManager()->getStorage('marked_question')->loadByProperties($properties);
    if(!empty($existing_items)) {
      \Drupal::logger('questions_tags_to_paragraphs_queue')->info("Skipped marked_question on UID=".$user_id.' question='.$marked_id.', it already exists');
    }else{
      $markedEntity = \Drupal::entityTypeManager()->getStorage('marked_question')->create($properties);
      $markedEntity->save();
      \Drupal::logger('questions_tags_to_paragraphs_queue')->info("Added marked_question on UID=".$user_id.' question='.$marked_id);
    }
  }
}
