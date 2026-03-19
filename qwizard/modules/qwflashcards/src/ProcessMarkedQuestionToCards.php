<?php


namespace Drupal\qwflashcards;


use Drupal\user\Entity\User;
use Drupal\Component\Serialization\Json;

class ProcessMarkedQuestionToCards {

  public static function processUpdateMarkedQuestions($id) {

    $profile =   \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->load($id);

    $marked_cards = Json::decode($profile->field_marked_cards->value);
    $marked_questions = Json::decode($profile->field_marked_questions->value);

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

    #var_dump($marked_questions);
    #var_dump($marked_cards); exit;
    $marked_items = array_merge(
      !empty($marked_questions) ? $marked_questions : [],
      !empty($marked_cards) ? $marked_cards : [],
    );

    $course_id = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
    if(count($marked_items)) {
      foreach($marked_items as $marked_id) {
        $markedEntity = \Drupal::entityTypeManager()->getStorage('marked_question')->create([
          'question' => $marked_id,
          'uid'      => $profile->get('uid')->getValue()[0]['target_id'],
          'course'   => $course_id
        ]);
        $markedEntity->save();
      }
    }
  }

  public static function finishUpdateMarkedQuestions($results,$success,$operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One User processed.', '@count user processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }
}
