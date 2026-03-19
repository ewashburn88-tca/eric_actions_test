<?php

namespace Drupal\qwmarked;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Set defaults for marked questions class field
 * Can call from /devel/php with the below
 * \Drupal::service('qwmarked.setMarkedQuestionDefaultClass')->setForUser($uid);
 */
class SetMarkedQuestionDefaultClass
{
  protected $user = null;
  protected $qwizardGeneral = null;
  protected $qwsubs = null;

  public function __construct(QwizardGeneral $qwizardGeneral, SubscriptionHandler $qwsubs)
  {
    $this->qwizardGeneral = $qwizardGeneral;
    $this->qwsubs = $qwsubs;
  }

  public static function create(ContainerInterface $container): SetMarkedQuestionDefaultClass
  {
    return new static(
      $container->get('qwizard.general'),
      $container->get('qwsubs.subscription_handler'),
    );
  }


  /**
   * To test - \Drupal::service('qwmarked.setMarkedQuestionDefaultClass')->setForUser(57296);
   * @param $user
   * @return void
   */
  public function setForUser($user)
  {
    if(is_int($user)){
      $user = User::load($user);
    }
    $this->user = $user;

    // Skip this user if they don't have any marked questions. Helps in case they got added to queue twice
    $con   = \Drupal\Core\Database\Database::getConnection();
    $query = $con->select('marked_question_field_data', 'mq');
    $query->fields('mq', ['id', 'course', 'uid']);
    $query->condition('uid', $user->id());
    $query->isNull('mq.course');
    $existing_marked_cards_with_null_course_for_user = $query->execute()->fetchAll(\PDO::FETCH_OBJ);
    if(empty($existing_marked_cards_with_null_course_for_user)){
      return;
    }


    $state = $this->getUserState();

    switch($state['state']){
      case 'inactive_single_course':
      case 'active_single_course':
        $this->handleSingleCourse($state);
        break 1;
      case 'active_multiple_courses':
      case 'inactive_multiple_courses':
      case 'mixed_active_inactive_courses':
        $this->handleMultipleCourses($state);
        break 1;
      case 'no_subscriptions':
        // nothing to do
        break 1;
    }


    /*$query = \Drupal::database()->update('marked_question_field_data');
    $query->fields(['course' => $course, 'changed' => time()]);
    $query->condition('uid', $this->user->id());
    $query->condition('status', 1);
    $query->execute();*/
  }

  /**
   * Just set all their existing marked questions to their one course
   * @param $state
   * @return void
   */
  public function handleSingleCourse($state){
    $course_id = reset($state['all_courses']);
    $query = \Drupal::database()->update('marked_question_field_data');
    $query->fields(['course' => $course_id, 'changed' => time()]);
    $query->condition('uid', $this->user->id());
    //$query->condition('status', 1);
    $rows_affected = $query->execute();
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['marked_question_list']);
  }

  public function handleMultipleCourses($state){
    // First assign everything to a single course
    $primary_course_id = reset($state['all_courses']);
    $this->handleSingleCourse($state);

    // clone all their existing marked questions for each remaining course
    $remaining_courses = [];
    $i = 0;
    foreach($state['all_courses'] as $course_id){
      if($i > 0){
        $remaining_courses[$course_id] = $course_id;
      }
      $i++;
    }

    foreach($remaining_courses as $remaining_course){
      $this->duplicateMarkedQuestionsToCourseForUser($primary_course_id, $remaining_course);
    }

    // Clean up data, remove marked questions in courses where they are not allowed
    #$this->removeInvalidMarkedQuestions();
  }

  public function removeInvalidMarkedQuestions(){
    $all_courses = $this->qwizardGeneral->getStatics('all_courses');
    foreach($all_courses as $course_id=>$course_name){
      $options = [
        'status' => 0,
        'type' => 'all',
        'user' => $this->user->id(),
        'course' => $course_id,
        'as_question_ids' => false,
      ];

      $marked_questions = $this->qwizardGeneral->getMarkedQuestions($options);
      $questions_to_load = [];
      foreach($marked_questions as $marked_question){
        $question_id = $marked_question->getQuestionID();
        $questions_to_load[$question_id] = $question_id;
      }
      $questions = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($questions_to_load);

      foreach($marked_questions as $marked_question) {
        $belongs = $this->doesQuestionBelongInCourse($questions[$marked_question->getQuestionID()], $course_id);
        if(!$belongs){
          //$marked_question->delete();
          \Drupal::logger('SetMarkedQuestionDefaultClass')->notice('User '.$this->user->id().' has
          marked question '.$marked_question->id().' for course '.$course_name.'
          , but that question does not belong in that course. It should be deleted');
        }
      }
    }

  }

  public function doesQuestionBelongInCourse($question, $course_id){
    $belongs = false;
    $question_courses = [];
    foreach($question->get('field_courses')->getValue() as $course_id) {
      $course_id = $course_id['target_id'];
      $question_courses[$course_id] = $course_id;
    }

    if(in_array($course_id, $question_courses)){
      $belongs = true;
    }

    return $belongs;
  }

  public function duplicateMarkedQuestionsToCourseForUser($primary_course_id, $remaining_course){
    $options = [
      'status' => 'all',
      'type' => 'all',
      'user' => $this->user->id(),
      'course' => $primary_course_id,
      'as_question_ids' => false,
    ];
    $replicator = \Drupal::service('replicate.replicator');
    $marked_questions = $this->qwizardGeneral->getMarkedQuestions($options);

    $questions_to_load = [];
    foreach($marked_questions as $marked_question){
      $question_id = $marked_question->getQuestionID();
      $questions_to_load[$question_id] = $question_id;
    }
    $questions = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($questions_to_load);

    $options['course'] = $remaining_course;
    $marked_questions_in_new_course = $this->qwizardGeneral->getMarkedQuestions($options);
    if(count($marked_questions) == count($marked_questions_in_new_course)){
      \Drupal::logger('SetMarkedQuestionDefaultClass')->warning('User '.$this->user->id().'
      had '.count($marked_questions).' marked questions in course '.$remaining_course.'
      , already');
      return;
    }

    $questions_that_dont_belong_in_remaining_course = [];
    $questions_that_exist_already = [];
    foreach($marked_questions as $existing_marked_question){
      $question_id = $existing_marked_question->getQuestionID();
      if($this->doesMarkedQuestionExistAlready($question_id, $remaining_course)) {
        $questions_that_exist_already[$existing_marked_question->id()] = $existing_marked_question->id();
      }
      elseif(!$this->doesQuestionBelongInCourse($questions[$existing_marked_question->getQuestionID()], $remaining_course)) {
        $questions_that_dont_belong_in_remaining_course[$existing_marked_question->id()] = $existing_marked_question->id();
      }else{
        $new_marked_question = $replicator->cloneEntity($existing_marked_question);
        $new_marked_question->setCourseId($remaining_course);

        $new_marked_question->save();
        //$cache_tag = $existing_marked_question->getEntityTypeId() . ':' . $existing_marked_question->id();
      }
    }

    \Drupal::service('cache_tags.invalidator')->invalidateTags(['marked_question_list']);

    $options['course'] = $remaining_course;
    $options['loaded'] = false;
    $marked_questions_in_new_course = $this->qwizardGeneral->getMarkedQuestions($options);
    if(count($marked_questions) !=
      (count($questions_that_exist_already) + count($questions_that_dont_belong_in_remaining_course) + count($marked_questions_in_new_course))){
      \Drupal::logger('SetMarkedQuestionDefaultClass')->warning('User '.$this->user->id().'
      had '.count($marked_questions).' marked questions in course '.$primary_course_id.'
      , but had '.count($marked_questions_in_new_course).' in '.$remaining_course.'.
      These should be equal after duplication. Questions that did not belong were '.count($questions_that_dont_belong_in_remaining_course));
    }
  }

  public function doesMarkedQuestionExistAlready($question_id, $course_id){
    $exists = false;
    $options = [
      'status' => 'all',
      'type' => 'all',
      'user' => $this->user->id(),
      'course' => $course_id,
      'loaded' => false,
      'question_id' => $question_id,
      'as_question_ids' => false
    ];
    $existing_questions = $this->qwizardGeneral->getMarkedQuestions($options);
    if(!empty($existing_questions)){
      $exists = true;
    }


    return $exists;
  }

  public function getUserState(){
    $state = ['state' => '', 'active_courses' => [], 'all_courses' => []];
    //$user_states_possible = ['no_subscriptions', 'inactive_single_course', 'active_single_course', 'active_multiple_courses',  'inactive_multiple_courses', 'mixed_active_inactive_courses'];

    // Find courses that the user has been active to, and is active to
    $subscriptionHandler = \Drupal::service('qwsubs.subscription_handler');
    $all_subscriptions = $subscriptionHandler->getUserSubscriptions($this->user->id(), null, false);
    foreach ($all_subscriptions as $subscription) {
      $course_id = $subscription->getCourseId();
      $state['all_courses'][$course_id]   = $course_id;
    }
    $active_subscriptions = $subscriptionHandler->getUserSubscriptions($this->user->id(), null, false);
    foreach ($active_subscriptions as $subscription) {
      $course_id = $subscription->getCourseId();
      $state['active_courses'][$course_id]   = $course_id;
    }
    $all_courses_count = count($state['all_courses']);
    $active_courses_count = count($state['active_courses']);


    if(empty($all_courses_count)){
      $state['state'] = 'no_subscriptions';
    }
    elseif($all_courses_count == 1 && empty($active_courses_count)){
      $state['state'] = 'inactive_single_course';
    }
    elseif($all_courses_count == 1 && $active_courses_count == $all_courses_count){
      $state['state'] = 'active_single_course';
    }
    elseif($all_courses_count > 1){
      if($active_courses_count == $all_courses_count){
        $state['state'] = 'active_multiple_courses';
      }
      elseif(empty($active_courses_count) && !empty($all_courses_count)){
        $state['state'] = 'inactive_multiple_courses';
      }
      else{
        $state['state'] = 'mixed_active_inactive_courses';
      }
    }
    if(empty($state['state'])){
      \Drupal::logger('SetMarkedQuestionDefaultClass')->warning('User '.$this->user->id().' was not able to have their state detected');
    }

    return $state;
  }
}
