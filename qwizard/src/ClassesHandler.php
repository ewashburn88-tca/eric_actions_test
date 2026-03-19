<?php

namespace Drupal\qwizard;

use Drupal\taxonomy\Entity\Term;

/**
 * Class ClassesHandler.
 */
class ClassesHandler implements ClassesHandlerInterface {

  /**
   * Constructs a new ClassesHandler object.
   */
  public function __construct() {

  }

  /**
   * Returns a list of classes for a given course.
   *
   * @param mixed $course
   * @param bool  $loaded
   *    If true returns array of Terms. Otherwise a simple array of term ids.
   *
   * @return mixed
   */
  public static function getClassesInCourse($course, $loaded = FALSE) {
    $course_id = ($course instanceof Term) ? $course->id() : $course;
    $query     = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'classes')
      ->condition('status', '1')
      ->condition('field_course', $course_id);
    $classes   = $query->execute();
    if ($loaded) {
      $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
      $classes     = $termStorage->loadMultiple($classes);
    }
    return $classes;
  }

  /**
   * Returns a list of quizzes for a given class.
   *
   * @param mixed $class
   * @param bool  $loaded
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|int|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getQwizzesInClass($class, bool $loaded = FALSE) {

    $class_id = ($class instanceof Term) ? $class->id() : $class;
    $query    = \Drupal::entityQuery('qwiz')
      ->condition('class', $class_id)
      ->condition('status', 1);
    $qwizzes  = $query->execute();
    if ($loaded) {
      $termStorage = \Drupal::entityTypeManager()->getStorage('qwiz');
      $qwizzes     = $termStorage->loadMultiple($qwizzes);
    }
    return $qwizzes;
  }

}
