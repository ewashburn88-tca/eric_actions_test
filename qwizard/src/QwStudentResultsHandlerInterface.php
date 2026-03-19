<?php

namespace Drupal\qwizard;

use Drupal\qwsubs\Entity\Subscription;
use Drupal\user\Entity\User;
use \Drupal\taxonomy\Entity\Term;

/**
 * Interface QwStudentResultsHandlerInterface.
 */
interface QwStudentResultsHandlerInterface {

  /**
   * Retrieves a students results.
   *
   * @param \Drupal\user\Entity\User           $acct
   * @param \Drupal\qwsubs\Entity\Subscription $subscription
   *
   * @return array
   */
  public static function getStudentResults(User $acct, Subscription $subscription);

  /**
   * Initializes a students results.
   *
   * @param \Drupal\user\Entity\User           $acct
   * @param \Drupal\qwsubs\Entity\Subscription $subscription
   */
  public static function initStudentResults(User $acct, Subscription $subscription);

  /**
   * Rebuilds a students overall results.
   *
   * @param                                    $acct
   * @param \Drupal\qwsubs\Entity\Subscription $subscription
   */
  public static function rebuildStudentResults($acct, Subscription $subscription, Term $class, bool $include_inactive, bool $secondary_classes_only, bool $force_save);

}
