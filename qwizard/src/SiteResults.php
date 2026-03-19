<?php

namespace Drupal\qwizard;

use Drupal\social_media_links\Plugin\SocialMediaLinks\Platform\Drupal;
use Drupal\user\Entity\User;
use Drupal\qwizard\Entity\Qwiz;
use Drupal\qwizard\Entity\QwizInterface;
use Drupal\qwizard\Entity\QwPoolInterface;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\PermissionHandlerInterface;
use \Drupal\taxonomy\Entity\Term;

/**
 * Class SiteResults.
 */
class SiteResults implements SiteResultsInterface {
  // @todo should be dynamic
  // check ZukuGeneral::getPrimaryClassesForCourse($courseObject->id()), using $mode
  //protected array $test_classes = ['navle' => [460], 'bcse' => [461], 'vtne' => [462]];
  protected array $test_classes = [200 => [460], 201 => [461], 202 => [462]];

  /**
   * Constructs a new SiteResults object.
   */
  public function __construct() {

  }

  /**
   * Gets the total summed quiz results for a quiz.
   *
   * @param      $quiz
   * @param null $start_date
   * @param null $end_date
   *
   * @return array
   */
  public function getResultsForQuiz($quiz, $start_date = NULL, $end_date = NULL) {
    $qwiz_results = QwizResult::getAllResultsForQwiz($quiz, $this->account, $start_date, $end_date);
    return $this->tallyResults($qwiz_results);
  }

  /**
   * {@inheritDoc}
   */
  public function getResultsForCourses($course, $type, $force = false, $start_date = NULL, $end_date = NULL) {
    if (!$force && $cache = \Drupal::cache()->get('statics_' . $type. '_' . $course)) {
      return $cache->data;
    }
    else {
      $query = \Drupal::entityQuery('qwiz_result')
        ->condition('course', $course);

      //requires DrupalDateTime objects formatted
      if ($start_date) {
        $query->condition('start', $start_date, '<=');
      }
      if ($end_date) {
        $query->condition('end', $end_date, '>=');
      }

      $qrids        = $query->execute();
      $storage      = \Drupal::entityTypeManager()->getStorage('qwiz_result');
      $qwiz_results = $storage->loadMultiple($qrids);
      $avg = $this->tallyResults($qwiz_results);
      \Drupal::cache()->set('statics_' . $type . '_' .$course, $avg, strtotime('+1 day'));
      return $avg;
    }
  }

  /**
   * Gets the total summed quiz results for the user.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param null                         $start_date ISO
   * @param null                         $end_date   ISO
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTotalResults($course, $start_date = NULL, $end_date = NULL) {
    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('user_id', $this->account->id());

    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('qwsubs')) {
      $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
      $sid = $subscriptions_service->getCurrentSubscription($course, $this->account->id());
      $query->condition('subscription_id', $sid->id());
    }
    if ($start_date) {
      $query->condition('end', $start_date, '>=');
    }
    if ($end_date) {
      $query->condition('start', $end_date, '>=');
    }

    $qrids = $query->execute();

    $storage      = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $qwiz_results = $storage->loadMultiple($qrids);

    return $this->tallyResults($qwiz_results);
  }

  /**
   * Gets the total summed quiz results for a pool.
   *
   * @param \Drupal\qwizard\Entity\QwPoolInterface $pool
   * @param null                                   $start_date
   * @param null                                   $end_date
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getResultsForPoolType($pool_type, $start_date = NULL, $end_date = NULL) {
    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('user_id', $this->account->id())
      // @todo pool not defined here
      ->condition('qwiz_id', $pool->getQwizzesInPool(), 'IN');

    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('qwsubs')) {
      $course = $pool->getCourse();
      $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
      $sid = $subscriptions_service->getCurrentSubscription($course, $this->account->id());
      $query->condition('subscription_id', $sid->id());
    }
    if ($start_date) {
      $query->condition('end', $start_date, '>=');
    }
    if ($end_date) {
      $query->condition('start', $end_date, '>=');
    }

    $qrids = $query->execute();

    $storage      = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $qwiz_results = $storage->loadMultiple($qrids);

    return $this->tallyResults($qwiz_results);
  }

  /**
   * Puts the results into an array of totals.
   *
   * @param $qwiz_results
   *     Array of loaded QwResults.
   *
   * @todo: This function should cache results, likely to the qw_student_results
   *      entity, which needs to be further developed.
   *
   * @return array
   *   $results['score_attempted']
   *   $results['score_seen']
   *   $results['score_all']
   *   $results['total_questions']
   *   $results['attempted']
   *   $results['seen']
   *   $results['correct']
   */
  public function tallyResults($qwiz_results) {
    $results         = [];
    $score_attempted = 0;
    $score_seen      = 0;
    $score_all       = 0;
    $total_questions = 0;
    $attempted       = 0;
    $seen            = 0;
    $correct         = 0;
    $count           = 0;

    $test_score_attempted = 0;
    $test_score_seen      = 0;
    $test_score_all       = 0;
    $test_total_questions = 0;
    $test_attempted       = 0;
    $test_seen            = 0;
    $test_correct         = 0;
    $test_count           = 0;
    $test_classes = [200 => [460], 201 => [461], 202 => [462]];
    foreach ($qwiz_results as $qwiz_result) {
      // Determine if the result belongs in ['test_mode'] results or not
      $qwiz_class = $qwiz_result->class->target_id;
      $qwiz_course = $qwiz_result->course->target_id;

      if (in_array($qwiz_class, $test_classes[$qwiz_course])) {
        //Test Mode Results
        $test_count++;
        $test_score_attempted += $qwiz_result->score_attempted->value;
        $test_score_seen += $qwiz_result->score_seen->value;
        $test_score_all += $qwiz_result->score_all->value;
        $test_total_questions += $qwiz_result->total_questions->value;
        $test_attempted += $qwiz_result->attempted->value;
        $test_seen += $qwiz_result->seen->value;
        $test_correct += $qwiz_result->correct->value;
      } else {
        // Normal Results, Study Mode
        $count++;
        $score_attempted += $qwiz_result->score_attempted->value;
        $score_seen += $qwiz_result->score_seen->value;
        $score_all += $qwiz_result->score_all->value;
        $total_questions += $qwiz_result->total_questions->value;
        $attempted += $qwiz_result->attempted->value;
        $seen += $qwiz_result->seen->value;
        $correct += $qwiz_result->correct->value;
      }
    }

    // @Todo verify hard
    // Normals Results
    $results['score_attempted'] = empty($score_attempted) ? 0 : $score_attempted / $count;
    $results['score_seen']      = empty($score_seen) ? 0 : $score_seen / $count;
    $results['score_all']       = empty($score_all) ? 0 : $score_all / $count;
    $results['total_questions'] = $count;
    $results['attempted']       = $attempted;
    $results['seen']            = $seen;
    $results['correct']         = $correct;

    // Test Mode Results
    $results['test_mode']                    = [];
    $results['test_mode']['score_attempted'] = empty($test_score_attempted) ? 0 : $test_score_attempted / $test_count;
    $results['test_mode']['score_seen']      = empty($test_score_seen) ? 0 : $test_score_seen / $test_count;
    $results['test_mode']['score_all']       = empty($test_score_all) ? 0 : $test_score_all / $test_count;
    $results['test_mode']['total_questions'] = $test_total_questions;
    $results['test_mode']['attempted']       = $test_attempted;
    $results['test_mode']['seen']            = $test_seen;
    $results['test_mode']['correct']         = $test_correct;

    return $results;
  }

}
