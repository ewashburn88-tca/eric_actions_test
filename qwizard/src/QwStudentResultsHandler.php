<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\qwizard\Entity\QwStudentResults;
use Drupal\user\Entity\User;
use Drupal\qwsubs\Entity\Subscription;
use Drupal\qwizard\Entity\Qwiz;
use Drupal\qwizard\Entity\QwizInterface;
use Drupal\qwizard\Entity\QwPoolInterface;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\PermissionHandlerInterface;
use \Drupal\taxonomy\Entity\Term;

/**
 * Class QwStudentResultsHandler.
 */
class QwStudentResultsHandler implements QwStudentResultsHandlerInterface {

  /**
   * Constructs a new QwStudentResultsHandler object.
   */
  public function __construct() {

  }

  /**
   * Retrieves a students results.
   *
   * @param \Drupal\user\Entity\User                $acct
   * @param \Drupal\qwsubs\Entity\Subscription|NULL $subscription
   * @param \Drupal\taxonomy\Entity\Term|NULL       $class
   * @param bool                                    $include_inactive
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getStudentResults(User $acct, Subscription $subscription = NULL, Term $class = NULL, bool $include_inactive = FALSE) {
    $query = \Drupal::entityQuery('qw_student_results')
      ->condition('user_id', $acct->id());
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('qwsubs') && !empty($subscription)) {
      $query->condition('subscription_id', $subscription->id());
    }
    if (!$include_inactive) {
      $query->condition('status', 1);
    }
    if (!empty($class)) {
      $query->condition('class', $class->id());
    }
    $results = $query->execute();

    // Sort by ID DESC in PHP, it's faster than doing in DB on a large table and expected results are only ~10
    // Sort is only needed due to multiples existing, we want the most recent
    uasort($results, function ($a, $b) {
      return ($a < $b);
    });

    // BUG - Sometimes a student may have duplicate active results. In this case grab the most recent one
    // Can be removed if AutoFixers.php is run on cron
    // @todo Is this still needed?
    $line_items = \Drupal::entityTypeManager()
      ->getStorage('qw_student_results')
      ->loadMultiple($results);
    $unique_classes = [];
    $unique_results = [];
    $duplicates_found = 0;
    foreach ($line_items as $line_item) {
      $class_id = $line_item->class->target_id;
      if (empty($class_id)) {
        $class_id = 0;
      }

      if (empty($unique_classes[$class_id])) {
        $unique_classes[$class_id] = 1;
        $unique_results[$line_item->id()] = $line_item->id();
      }
      else {
        $duplicates_found = 1;
      }
    }
    if ($duplicates_found) {
      // Can turn on logging if Autofixers.php is ran regularly
      //\Drupal::logger('qw_student_results')->notice('Duplicate active qw_student_results were found for '.$acct->id().' and subscription '.$subscription->id());
    }

    return $unique_results;
  }

  /**
   * Initializes a students results.
   *
   * @todo: // @todo: Fragile, if result bundles are altered this code my fail.
   *
   * @param \Drupal\user\Entity\User           $acct
   * @param \Drupal\qwsubs\Entity\Subscription $subscription
   *
   * @return array|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function initStudentResults(User $acct, Subscription $subscription, $actually_save = TRUE) {
    // @todo: Add error handling.
    $results = [];
    $course_id = $subscription->getCourseId();
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $course = $term_storage->load($course_id);
    // Create the empty result records.
    // Get all classes.
    $classesHandler = \Drupal::service('qwizard.classeshandler');
    $classes = $classesHandler->getClassesInCourse($course, TRUE);
    foreach ($classes as $class_id => $class) {
      $data = [
        'name' => preg_replace('/\s+/', '_', $acct->name->value . '-' . $class->name->value . '-results'),
        'class' => $class_id,
        'course' => $course_id,
        'user_id' => $acct->id(),
        'subscription_id' => $subscription->id(),
        'type' => 'class_results',
      ];
      $studentResults = \Drupal::entityTypeManager()
        ->getStorage('qw_student_results')
        ->create($data);
      $qwizzes = $classesHandler->getQwizzesInClass($class_id);
      $results_array = [];
      foreach ($qwizzes as $qwiz) {
        $results_array[$qwiz] = [];
      }
      $studentResults->setResults($results_array);
      if ($actually_save) {
        $studentResults->save();
        $results[$studentResults->id()] = $studentResults;
      }
      else {
        $results[$class_id] = $studentResults;
      }

    }
    $data = [
      'name' => preg_replace('/\s+/', '_', $acct->name->value . '-' . $course->name->value . '-Course-results'),
      'course' => $course_id,
      'user_id' => $acct->id(),
      'subscription_id' => $subscription->id(),
      'type' => 'course_results',
    ];
    $studentResults = \Drupal::entityTypeManager()
      ->getStorage('qw_student_results')
      ->create($data);
    $studentResults->setResults(['overall_' . $course_id => []]);
    if ($actually_save) {
      $studentResults->save();
      $results[$studentResults->id()] = $studentResults;
    }
    else {
      $results['overall_' . $course_id] = $studentResults;
    }

    #dpm($results);
    return $results;
  }

  /**
   * Rebuilds a students overall results.
   *
   * @param                                    $acct
   * @param \Drupal\qwsubs\Entity\Subscription $subscription
   * @param \Drupal\taxonomy\Entity\Term|NULL  $class
   * @param bool                               $include_inactive
   * @param bool                               $secondary_classes_only
   * @param                                    $force_save
   *
   * @return void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function rebuildStudentResults($acct, Subscription $subscription, Term $class = NULL, bool $include_inactive = FALSE, bool $secondary_classes_only = FALSE, $force_save = TRUE) {

    if (empty($subscription->status->value) && !$include_inactive) {
      // Subscription is not active, don't bother updating results.
      return;
    }

    $results = self::getStudentResults($acct, $subscription, $class, $include_inactive);
    $storage = \Drupal::entityTypeManager()->getStorage('qw_student_results');
    if (empty($results)) {
      // Create the results.
      $sResults = self::initStudentResults($acct, $subscription);
    }
    else {
      $sResults = $storage->loadMultiple($results);
    }

    // Sometimes we only want to rebuild non-primary classes from QWMaintenance, this accounts for that
    $QWGeneral = \Drupal::service('qwizard.general');
    $statics = $QWGeneral->getStatics();
    if ($secondary_classes_only) {
      foreach ($sResults as $key => $value) {
        $class_id = $value->class->target_id;
        if (in_array($class_id, $statics['study_test_classes'])) {
          unset($sResults[$key]);
        }
      }
    }

    self::rebuildMultipleResults($acct, $subscription, $sResults, $force_save);
  }

  /**
   * Rebuilds a provided student results.
   *
   * @param      $user
   * @param      $subscription
   * @param      $student_results_to_update
   * @param bool $force_save
   *
   * @return void
   */
  public static function rebuildMultipleResults($user, $subscription, $student_results_to_update, bool $force_save = TRUE) {

    foreach ($student_results_to_update as $sResult) {
      $class_id = $sResult->getClassId();
      /*if (empty($results_by_class[$class_id])) {
        // Students will not always have results for all classes. This is fine
        $results_by_class[$class_id] = [];
      }*/
      if (qwizard_in_debug_mode()) {
        $start_time = microtime(TRUE);
      }

      $existing_json = $sResult->getResultsJson('string');
      $sResult->rebuildResults();
      $new_json = $sResult->getResultsJson('string');

      // Only make the save->() call if new results != old results
      // @todo: ZUKU-1821 - This is what was causing the issue, why did we do it this way?
      // If the sResults were deleted during maint, this should always save.
      if ($force_save || (!empty($new_json) && $existing_json != $new_json)) {
        $sResult->save();
      }

      if (qwizard_in_debug_mode()) {
        $execution_time = (microtime(TRUE) - $start_time);
        \Drupal::logger('qw_student_results')
          ->debug('Class ' . $class_id . ' with type of ' . $sResult->getClassType($class_id) . ' took ' . $execution_time . ' seconds to rebuild');
      }
    }
  }

}
