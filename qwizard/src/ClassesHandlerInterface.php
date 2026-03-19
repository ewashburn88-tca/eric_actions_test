<?php

namespace Drupal\qwizard;

use Drupal\taxonomy\Entity\Term;

/**
 * Interface ClassesHandlerInterface.
 */
interface ClassesHandlerInterface {

  /**
   * Returns a list of classes for a given course.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param bool                         $loaded
   *    If true returns array of Terms. Otherwise a simple array of term ids.
   *
   * @return mixed
   */
  public static function getClassesInCourse(Term $course, $loaded = FALSE);

  /**
   * Returns a list of quizzes for a given class.
   *
   * @param mixed $class
   * @param bool  $loaded
   *
   * @return mixed
   */
  public static function getQwizzesInClass($class, bool $loaded = FALSE);

}
