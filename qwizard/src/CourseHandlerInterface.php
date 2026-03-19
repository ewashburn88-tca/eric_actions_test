<?php

namespace Drupal\qwizard;

/**
 * Interface CourseHandlerInterface.
 */
interface CourseHandlerInterface {

  /**
   * Gets the user's current course.
   *
   * @return \Drupal\taxonomy\Entity\Term
   */
public function getCurrentCourse ();

  /**
   * Sets the user's current course.
   *
   * @param $course
   * @param $user
   *
   * @return \Drupal\taxonomy\Entity\Term
   */
  public function setCurrentCourse (\Drupal\taxonomy\Entity\Term $course);

}
