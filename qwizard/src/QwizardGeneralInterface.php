<?php

namespace Drupal\qwizard;

use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserInterface;

/**
 * Interface QwizardGeneralInterface.
 */
interface QwizardGeneralInterface {

  /**
   * Returns an array of content types that are questions for quiz wizard.
   *
   * @return array ['type_machine_name' => 'label']
   */
  public static function getListOfQuestionTypes();

  /**
   * Retrieves a list of question ids as from entity query.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param array                        $question_types
   *
   * @return array|int
   */
  public static function getAllQuestionIdsForCourse(Term $course, $question_types = []);



  /**
   * Formats string date or timestamp to ISO datetime.
   *
   * @param        $datetime
   * @param string $tz
   *
   * @return string
   * @throws \Exception
   */
  public static function formatIsoDate($datetime, $tz = 'UTC');


  /**
   * Formats string date or timestamp to ISO datetime.
   *
   * @param        $datetime
   * @param string $tz
   *
   * @return string
   * @throws \Exception
   */
  public static function getDateTime($datetime, $tz = 'UTC');

  /**
   * Takes an unknown value for a user account and returns an AccountInterface.
   *
   * @param null|int|\Drupal\user\User $var
   *
   * @return FALSE|AccountInterface|UserInterface
   *   At least an AccountInterface.
   */
  public static function getAccountInterface($var);

  /**
   * Impements transformRootRelativeUrlsToAbsolute() using base url from site.
   *
   * @param $html
   *
   * @return string
   */
  public static function transformRootRelativeUrlsToAbsolute($html);

}
