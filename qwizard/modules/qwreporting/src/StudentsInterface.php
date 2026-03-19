<?php

namespace Drupal\qwreporting;

/**
 *
 */
interface StudentsInterface {

  /**
   * Get student data from a given course and student id.
   *
   * @param int $course
   *   Id of course/Taxonomy.
   * @param $student
   *   Id of student/User, or their user object
   *
   * @return array
   *   List of data from the student and group.
   */
  public function getStudentData(int $course, $student):array;

  /**
   * Get an array of students from a given group.
   *
   * @param int $group_id
   *   Id of the group.
   *
   * @return array
   *   Empty or with the students.
   */
  public function getStudents(int $group_id, $selected_topic = null, $since = null):array;

}
