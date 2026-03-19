<?php

namespace Drupal\qwizard;

use Drupal\qwsubs\SubscriptionHandler;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CourseHandler.
 */
class CourseHandler implements CourseHandlerInterface {
  private QwizardGeneral $QWGeneral;
  private SubscriptionHandler $subscription_handler;
  private $tempstore_service;
  private $tempstore_collection = 'UserCourseInfo';
  private $tempstore_key = 'current_course';

  public function __construct(QwizardGeneral $QwizardGeneral, SubscriptionHandler $subscription_handler, $tempstore_service){
    $this->QWGeneral = $QwizardGeneral;
    $this->subscription_handler = $subscription_handler;
    $this->tempstore_service = $tempstore_service;
  }

  public function create(ContainerInterface $container)
  {
    return new static(
      $container->get('qwizard.general'),
      $container->get('qwsubs.subscription_handler'),
      $container->get('tempstore.private')
    );
  }

  /**
   * Gets the user's current course.
   *
   * @return \Drupal\taxonomy\Entity\Term
   */
  public function getCurrentCourse() {
    $current_course = null;
    // First check get params
    if (!empty($_GET['course'])) {
      $current_course_name = $_GET['course'];
      // Load term.
      $properties     = ['name' => $current_course_name, 'vid' => 'courses'];
      $current_course = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties($properties);
      if(!empty($current_course)) {
        // Now set it in the session.
        $current_course = reset($current_course);
        self::setCurrentCourse($current_course);
      }
    }

    // Second check temporary storage for user
    #$tempstore->delete('current_course'); //Uncomment to test with a fresh cookie
    $tempstore_course = $this->getCurrentCourseFromTempstore();
    $all_courses = $this->QWGeneral->getStatics()['all_courses'];
    foreach($all_courses as $id=>$name){
      if(strtolower($name) == strtolower($tempstore_course)){
        $current_course = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->load($id);
        break;
      }
    }

    // All the above failed, use the first item from $subscriptions_service->getUserSubscriptions
    if(empty($current_course)){
      $current_subs = $this->subscription_handler->getUserSubscriptions(null, null, true);
      $current_sub = reset($current_subs);
      if(!empty($current_subs)) {
        $current_course_id = $current_sub->getCourseId();
        $current_course = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->load($current_course_id);
      }
    }

    // On success, set storage too
    if(!empty($current_course)) {
      \Drupal::service('qwizard.coursehandler')->setCurrentCourse($current_course);
    }


    return $current_course;
  }

  private function getCurrentCourseTempstoreCollection(){
    return $this->tempstore_service->get($this->tempstore_collection);
  }
  private function getCurrentCourseFromTempstore(){
    return strtolower($this->getCurrentCourseTempstoreCollection()->get($this->tempstore_key));
  }

  /**
   * Returns machine prefix for a course
   *
   */
  public function getCurrentCourseMachineName(){
    $course = $this->getCurrentCourse();
    $return = false;
    if(!empty($course)){
      $return = is_string($course) ? $course : strtolower($course->label());
    }

    return $return;
  }

  /**
   * Sets the user's current course.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   */
  public function setCurrentCourse($course) {
    $tempstore_value = $this->getCurrentCourseFromTempstore();

    // Make sure the user has a valid course first if saving as cookie
    $valid = false;
    $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
    $current_subs = $subscriptions_service->getUserSubscriptions(null, null, true);
    foreach($current_subs as $sub){
      if($sub->course->target_id == $course->id()){
        $valid = true;
        break;
      }
    }

    try {
      if ($valid) {
        // Only set if needed
        if(strtolower($tempstore_value) != strtolower($course->label())){
          $this->getCurrentCourseTempstoreCollection()->set($this->tempstore_key, $course->label());
       }
      } else {
        $this->getCurrentCourseTempstoreCollection()->delete($this->tempstore_key);
      }
    }catch(\Exception $e){
      \Drupal::logger('course_handler')->warning('Error on setting current_course in tempstore, was ignored. '.$e->getMessage().' | '.$e->getTraceAsString());
    }
  }

  public function setCurrentCourseByID($course_id){
    $courseObject = Term::load($course_id);
    $this->setCurrentCourse($courseObject);
  }

  /**
   * Get Active courses.
   * @return string[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getActiveCourses() {
    $courses = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadTree('courses', 0, NULL, TRUE);
    $final = [0 => '-Any-'];
    foreach ($courses as $course){
      $final[$course->id()] = $course->getName();
    }

    return $final;
  }
}
