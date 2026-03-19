<?php

namespace Drupal\qwizard;

use Drupal\Core\Datetime\Element\Datetime;
use Drupal\Core\Session\AccountInterface;
use Drupal\qwizard\Entity\QwPool;
use Drupal\qwsubs\Entity\SubTermInterface;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;
use Drupal\qwsubs\Entity\SubscriptionInterface;
use Drupal\qwsubs\Entity\MembershipInterface;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MembershipHandler.
 *
 * Define Membership as Subscription + Term + Question Pools.
 *   - User's must have all three active to take quizzes.
 *
 * @todo This should be moved to the qwsubs module.
 * @todo Needs descriptions added to docblocks.
 */
class MembershipHandler implements MembershipHandlerInterface {

  // @todo Add docblocks to properties.
  protected $acct;

  protected $course;

  protected $subscriptionHandler;

  protected $subTerm;

  protected int $premium = 0;

  protected int $special = 0;

  protected int $courseDuration = 335;

  /**
   * @param AccountInterface|null    $user
   * @param SubscriptionHandler|null $course
   */
  public function __construct(AccountInterface $user, SubscriptionHandler $course) {
    $this->acct = $user;
    $this->subscriptionHandler = $course;
  }

  /**
   * @param ContainerInterface $container
   *
   * @return MembershipHandler
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container): MembershipHandler {
    return new static(
      $container->get('current_user'),
      $container->get('qwsubs.subscription_handler')
    );
  }

  /**
   * @param AccountInterface|null $acct
   */
  public function setAcct(AccountInterface $acct): void {
    $this->acct = $acct;
  }

  /**
   * @param AccountInterface|null $acct
   */
  public function setAcctByUID(int $uid): void {
    $user = User::load($uid);
    if (!empty($user)) {
      $this->acct = $user;
    }
    else {
      throw new Exception('User ' . $uid . ' does not exist.');
    }

  }

  /**
   * @param Term|null $course
   */
  public function setCourse(Term $course): void {
    $this->course = $course;
  }

  /**
   * @param SubTermInterface|null $subTerm
   */
  public function setSubTerm(SubTermInterface $subTerm): void {
    $this->subTerm = $subTerm;
  }

  /**
   * @param mixed $premium
   */
  public function setPremium(int $premium): void {
    $this->premium = $premium;
  }

  /**
   * @param mixed $special
   */
  public function setSpecial(int $special): void {
    $this->special = $special;
  }

  /**
   * @param mixed $courseDuration
   */
  public function setCourseDuration(int $courseDuration): void {
    $this->courseDuration = $courseDuration;
  }

  /**
   * Creates a membership for a new user.
   */
  public function createNewSubscription($start = '', $end = '') {
    // Unsubscribe old subscriptions to this course
    $this->UnsubscribeToCourse($this->course->id());

    // Create Subscription.
    $subscription_id = $this->createSubscription('New membership', $start, $end);

    $subscription = $this->getSubscriptionDetails($subscription_id);

    // Pools.
    $this->createPools($subscription);

    return $subscription;
  }

  /**
   * Creates a copy of the last membership.
   *
   * @param int $subscription_id
   *
   * @return bool
   */
  public function createCopyOfLastMembership(int $subscription_id): bool {

    // Get subscription data
    $subscription = $this->getSubscriptionDetails($subscription_id);

    /*if($subscription->status->value == 1) {
      throw new Exception('Subscription is already active. ');
    }*/

    $this->UnsubscribeToCourse($subscription->course->target_id);

    try {

      // Create a membership same as the query one
      $this->createsNewMembership([
        'user_id' => $this->acct->id(),
        "start" => date('Y-m-d'),
        'end' => date('Y-m-d', strtotime("+ " . $subscription->max_term->value . " days")),
        'comment' => 'Restore membership from admin panel',
        'subscription_id' => $subscription_id,
      ]);

      // Activate subscription
      $subscription = $this->reactivateSubscription($subscription_id);

    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception($e->getMessage());
    }

    return TRUE;

  }

  public function createsNewMembershipForExistingSubscription($subscription_id, int $max_term = 335, string $comment = '', $new_pools = TRUE, $start = 'now', $end = NULL) {
    if (empty($end)) {
      $end = '+' . strval($max_term) . ' days';
    }

    $end_date_obj = QwizardGeneral::getDateTime($end);
    $now = new \DateTime('now');
    $max_future_date = (clone $now)->modify('+365 days');

    // Ensure the end date is not more than 1 year in the future.
    if ($end_date_obj > $max_future_date) {
      $end_date_obj = $max_future_date;
    }

    $end_date = date('Y-m-d', $end_date_obj->getTimestamp());
    $start_date = date('Y-m-d', QwizardGeneral::getDateTime($start)->getTimestamp());

    $subscription = \Drupal::entityTypeManager()
      ->getStorage('subscription')
      ->load($subscription_id);

    // Test that start/end dates worked
    if (empty($end_date) || empty($start_date)) {
      throw new Exception('Dates were not calculated correctly in createsNewMembershipForExistingSubscription() end=' . var_dump($end_date) . ' start=' . var_dump($start_date));
    }
    try {
      // Update subscription max_term to match new max_term too
      $this->setExistingSubscriptionMaxLength($subscription_id, $max_term, 'days_from_now');

      // Create a fresh membership
      $this->createsNewMembership([

        'user_id' => $this->acct->id(),
        'end' => $end_date,
        'start' => $start_date,
        'comment' => $comment,
        'subscription_id' => $subscription_id,
      ]);

      // Update pools
      if ($new_pools) {
        $this->createPools($subscription);
      }
      else {
        $this->reactivatePools($subscription);
      }

      // Give the user back course roles and reactive subscription if needed.
      // Check if user has special_product role so it can be added back in.
      $this->special = in_array('special_product', $this->acct->getRoles());
      $this->addRolesToUser($subscription->get('course')
        ->getValue()[0]['target_id'], $this->acct->id(), $subscription->getPremium(), $this->special);
      $this->reactivateSubscription($subscription->id());

    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception($e->getMessage());
    }

  }

  /**
   * Helper function to add roles back to a user given a course where needed
   *
   * @param $course_id
   * @param $user_id
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addRolesToUser($course_id, $user_id, $premium = FALSE, $special = FALSE) {
    $user = User::load($user_id);
    $role_added = FALSE;

    $course_roles_by_course_id = \Drupal::service('qwizard.general')
      ->getStatics('full_course_roles');
    $course_role = $course_roles_by_course_id[$course_id]['normal'];
    if (!$user->hasRole($course_role)) {
      $user->addRole($course_role);
      $role_added = TRUE;
    }

    if ($premium) {
      $course_prem_role = $course_roles_by_course_id[$course_id]['premium'];
      if (!$user->hasRole($course_prem_role)) {
        $user->addRole($course_prem_role);
        $role_added = TRUE;
      }
    }

    if ($special) {
      if (!$user->hasRole('special_product')) {
        $user->addRole('special_product');
        $role_added = TRUE;
      }
    }

    if ($role_added) {
      $user->save();
    }
  }

  /**
   * Extends a membership by a paid extension.
   *
   */
  public function renewMembership(int $subscription_id, int $membership_id = NULL, int $days = NULL, $comment = 'Renew membership') {
    $subscription = $this->getSubscriptionDetails($subscription_id);

    // End all old memberships
    $memberships = $this->getSubscriptionMembership($subscription_id);
    if (!empty($memberships)) {
      foreach ($memberships as $m) {
        $this->endMembership($m);
      }
    }

    // Activate subscription if needed
    if (!$subscription->isActive()) {
      $subscription = $this->reactivateSubscription($subscription_id);
      $this->reactivatePools($subscription);
    }

    // Add back user roles if needed
    $course_id = $subscription->get('course')->getValue()[0]['target_id'];
    // Check if user has special_product role so it can be added back in.
    $this->special = in_array('special_product', $this->acct->getRoles());
    $this->addRolesToUser($course_id, $this->acct->id(), $subscription->getPremium(), $this->special);

    // Prefer input for days, but use max_term of old subscription otherwise
    if (empty($days)) {
      $days = $subscription->max_term->value;
    }

    try {
      // create a membership same as the query one
      $this->createsNewMembership([
        'user_id' => $this->acct->id(),
        "start" => date('Y-m-d'),
        'end' => date('Y-m-d', strtotime("+ " . $days . " days")),
        'comment' => $comment,
        'subscription_id' => $subscription_id,
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Something wrong happened when trying to renew the subscription.");
    }
  }

  /**
   * Pause a Membership.
   *
   * @param int $subscription_id
   * @param int $membership_id
   *
   * @throws Exception
   */
  public function pauseMembership(int $subscription_id, int $membership_id) {
    // Check if subscription is ended.
    $membership = $this->getSubscriptionMembership($subscription_id);
    if (count($membership) > 0) {
      // If is on time, end the current membership, creates new one.
      try {
        if ($membership_id == $membership[0]) {
          $this->endMembership($membership[0]);
        }
      }
      catch (\Throwable $e) {
        \Drupal::logger('qwizard')
          ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
        throw new Exception("Something wrong happened when trying to renew the subscription.");
      }
    }
  }

  /**
   * Adminstratively extends a membership by given number of days.
   *
   * @param int        $id
   *   Subterm id.
   * @param int|string $days
   *   Ammount of days or date.
   * @param string     $tye
   *   type of the variable $days (days or date)
   */
  public function extendMembership($id, $days, $type) {
    $membership = \Drupal::entityTypeManager()
      ->getStorage('subterm')
      ->load($id);
    if ($type == 'days') {
      $date = strtotime($membership->end->value . "+" . $days . " days");
    }
    elseif ($type == 'days_from_now') {
      $date = strtotime("+" . $days . " days");
    }
    else {
      // $type = 'date'
      $date = strtotime($days);
    }
    try {
      $membership->set('end', date('Y-m-d', $date));
      $membership->save();
    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Was not possible to update the expiration date.");
    }

    // The membership is being extended into the future. Make sure to add back roles if needed
    if ($date > strtotime('now')) {
      // Make sure the subterm is marked as active
      $user = User::load($membership->get('user_id')
        ->getValue()[0]['target_id']);
      $subscription_id = $membership->get('subscription_id')
        ->getValue()[0]['target_id'];
      $subscription = \Drupal::entityTypeManager()
        ->getStorage('subscription')
        ->load($subscription_id);
      $course_id = $subscription->get('course')->getValue()[0]['target_id'];

      $this->reactivateSubscription($subscription->id());
      // Add roles to user.
      // Check if user has special_product role so it can be added back in.
      $this->special = in_array('special_product', $this->acct->getRoles());
      $this->addRolesToUser($subscription->get('course')
        ->getValue()[0]['target_id'], $this->acct->id(), $subscription->getPremium(), $this->special);
    }

  }

  public function setExistingSubscriptionMaxLength($id, $days, $type) {
    $membership = \Drupal::entityTypeManager()
      ->getStorage('subscription')
      ->load($id);
    if ($type == 'days') {
      // Adding days to existing subscription
      $days = $membership->max_term->value + $days;
    }
    elseif ($type == 'days_from_now') {
      // no changes - $days = $days;
    }
    try {
      $membership->max_term = $days;
      $membership->save();
    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Was not possible to update the expiration date.");
    }
  }

  /**
   * Gets one the memberships by $membership_id
   */
  public function getMembership($membership_id) {
    try {
      $membership = \Drupal::entityTypeManager()
        ->getStorage('subterm')
        ->load($membership_id);
    }
    catch (\Throwable $e) {
      throw new Exception($e->getMessage());
    }

    if ($membership && $membership->user_id->target_id == $this->acct->id()) {
      return $membership;
    }
  }

  /**
   *  Gets membership information for set acct. By default will only count
   *  memberships that have not expired.
   *
   * @param bool $exclude_expired
   * @param int  $for_course_id
   *
   * @return array|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserMemberships(bool $exclude_expired = TRUE, int $for_course_id = 0, $for_subscription_id = 0) {
    if (empty($this->acct->id())) {
      throw new \Exception('getUserMemberships was called but user ID was not set. Use setAcctByUID() before calling this');
    }
    $sub_storage = \Drupal::entityQuery('subterm');
    $sub_storage->condition('user_id', $this->acct->id());
    $sub_storage->sort('id', 'DESC');

    // @todo add sort on end or start to keep most recent item first
    if ($exclude_expired) {
      $sub_storage->condition('end', date('Y-m-d'), '>');
    }

    if ($for_subscription_id) {
      $sub_storage->condition('subscription_id', $for_subscription_id, '=');
    }

    $return_data = $sub_storage->execute();

    // if $for_course_id is set, unset any subscriptions that are not tied to the given course
    if (!empty($for_course_id) && !empty($return_data)) {
      foreach ($return_data as $key => $membership_id) {
        $membership_data = $this->getMembership($membership_id);
        if (empty($membership_data)) {
          throw new Exception('$membership_data had a subterm/membership ID that $MH->getUserMemberships could not load, bad data in DB');
        }
        $sub_id = $membership_data->get('subscription_id')
          ->getValue()[0]['target_id'];
        $sub_data = \Drupal::entityTypeManager()
          ->getStorage('subscription')
          ->load($sub_id);
        if (!empty($sub_data) && !empty($sub_data->get('course')->getValue())) {
          if ($sub_data->get('course')
              ->getValue()[0]['target_id'] != $for_course_id) {
            // This membership was for a different course, unset it
            unset($return_data[$key]);
          }
        }
      }
    }

    return array_values($return_data);
  }

  /**
   * Get all the user subscriptions.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws Exception
   */
  public function getSubscriptions() {
    $final = [];
    try {
      $courses = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree('courses', 0, NULL, TRUE);
      foreach ($courses as $course) {
        $subscriptions = $this->subscriptionHandler->getUserSubscriptions($this->acct->id(), $course);
        $membership = [];
        if (count($subscriptions) > 0) {
          foreach ($subscriptions as $subscription) {
            try {
              $membershipsids = $this->getAllSubscriptionMembership($subscription->id());
              if (count($membershipsids) > 0) {
                $membership[$subscription->id()] = \Drupal::entityTypeManager()
                  ->getStorage('subterm')
                  ->loadMultiple($membershipsids);
              }
            }
            catch (\Throwable $e) {
              $membership[$subscription->id()] = [];
            }
          }
        }
        $final[] = [
          'name' => $course->getName(),
          'courseid' => $course->id(),
          'userSubscription' => (count($subscriptions) > 0) ? $subscriptions : [],
          'membership' => $membership,
        ];
      }
    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception('User has no subscriptions or a problem happened when consulting them. Check Drupal log for more info.');
    }
    return $final;
  }

  /**
   * Gets the active memberships of the Subscription.
   *
   * Should return 1.
   */
  public function getSubscriptionMembership($subscription_id) {
    $membership = \Drupal::entityQuery('subterm');
    $membership->condition('subscription_id', $subscription_id);
    $membership->condition('end', date('Y-m-d'), '>');
    $membership_data = array_values($membership->execute());
    /*if (count($membership) > 1) {
      \Drupal::logger('qwizard')->error($e->getMessage().' | '.$e->getTraceAsString());
      throw new \Exception('The amount of subscriptions is more than allowed. Contact the administrator.');
    }*/
    return $membership_data;
  }

  /**
   * Given a course and a user, see if that user's last subscription is still
   * within the time limit for extensions Will return false if no subscription
   * exists
   */
  public function isLastSubscriptionRecentlyExpired($course_id, $return_subscription = FALSE) {
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $test_time = strtotime($QwizardGeneral->getStatics('subscription_expiration_time_limit'));
    //$test_time = time();

    $subscriptions_by_course = $this->getSubscriptions();
    $course_subscriptions = [];
    $is_recently_expired = FALSE;
    foreach ($subscriptions_by_course as $course_with_subscriptions) {
      if (!empty($course_with_subscriptions['userSubscription']) && $course_with_subscriptions['courseid'] == $course_id) {
        foreach ($course_with_subscriptions['userSubscription'] as $subscription) {
          $course_subscriptions[] = $subscription;
        }
      }
    }
    $window_date = date('Y-m-d', $test_time);
    foreach ($course_subscriptions as $subscription) {
      $expired= $subscription->get('status')->value;
      if ($expired) break;
      $membership_query = \Drupal::entityQuery('subterm');
      $membership_query->condition('subscription_id', $subscription->id());
      $membership_query->condition('end', $window_date, '>');
      $membership_ids = $membership_query->execute();
      if (!empty($membership_ids)) {
        $is_recently_expired = TRUE;
        if ($return_subscription) {
          return $subscription;
        }
        break;
      }
    }

    return $is_recently_expired;
  }

  /**
   * Gets the active memberships of the Subscription.
   *
   * Should return 1.
   */
  public function getAllSubscriptionMembership($subscription_id) {
    $membership = \Drupal::entityQuery('subterm');
    $membership->condition('subscription_id', $subscription_id);
    $membership->sort('end', 'DESC');
    $membership = array_values($membership->execute());
    return $membership;
  }

  /**
   * Get the subscription details.
   *
   * @param $subscription_id
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubscriptionDetails($subscription_id) {
    $sub_storage = \Drupal::entityTypeManager()
      ->getStorage('subscription')->load($subscription_id);

    return $sub_storage;
  }

  /**
   * Get the course from subscription id.
   *
   * @param $subscription_id
   *
   * @return mixed
   * @throws Exception
   */
  public function getCourseFromSubscription($subscription_id) {
    try {
      $subscription = $this->getSubscriptionDetails($subscription_id);
      $course = \Drupal::service('entity_type.manager')
        ->getStorage('taxonomy_term')
        ->load($subscription->get('course')->target_id);
    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Was not possible to query the subscription.");
    }
    return $course;
  }

  /**
   * Given a course id and $this->acct being set, returns if user is already
   * assigned to that course as a count (0-2+)
   *
   * @param int  $course_id
   * @param bool $exclude_expired
   *
   * @return int
   * @throws Exception
   */
  public function isUserSubscribedToCourse(int $course_id, bool $exclude_expired = TRUE, $subscription_id = 0, $only_count_renewals = FALSE): int {
    $membership_exists = 0;
    // Note that getMemberships gets expired courses as well
    if (!empty($subscription_id)) {
      $user_active_memberships = $this->getUserMemberships($exclude_expired, $course_id, $subscription_id);
    }
    else {
      $user_active_memberships = $this->getUserMemberships($exclude_expired, $course_id);
    }
    // No need to continue.
    if (empty($user_active_memberships)) {
      return 0;
    }

    // Sometimes we only care about purchased membership count, like for
    // extensions. In that case, unset ones that are admin created.
    if ($only_count_renewals) {
      $memberships_loaded = \Drupal::entityTypeManager()
        ->getStorage('subterm')
        ->loadMultiple($user_active_memberships);
      foreach ($user_active_memberships as $key => $membership_id) {
        $membership = $memberships_loaded[$membership_id];
        if (!$membership->isRenewal()) {
          unset($user_active_memberships[$key]);
        }
      }
    }

    foreach ($user_active_memberships as $active_membership_id) {
      $active_membership_data = $this->getMembership($active_membership_id);
      if (empty($active_membership_data)) {
        throw new Exception('$user_active_memberships had a subterm/membership ID that $this->getMembership could not load, bad data in DB');
      }

      $active_sub_id = $active_membership_data->get('subscription_id')
        ->getValue()[0]['target_id'];
      $active_course_data = $this->getCourseFromSubscription($active_sub_id);
      $active_course_id = (int) $active_course_data->id();

      if ($active_course_id == $course_id) {
        $membership_exists++;
      }
    }

    return $membership_exists;
  }

  /**
   * Creates the subscription for the user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createSubscription($comment = '', $start_date = '', $expire_date = '') {
    // Check if user has special_product role so it can be added back in.
    $this->special = in_array('special_product', $this->acct->getRoles());
    $sub_params = [
      'type' => 'term',
      'name' => $this->acct->name->value . '-' . $this->course->id() . '-subscription',
      'active' => 1,
      'max_term' => $this->courseDuration,
      'course' => $this->course->id(),
      'data' => [],
      'start' => $start_date,
      'end' => $expire_date,
      'premium' => $this->premium,
      'special' => $this->special,
      'comment' => $comment,
    ];
    \Drupal::logger('qwizard')
      ->notice('Creating new subscription: ' . $this->acct->name->value . ' in ' . $this->course->id());
    try {
      // We are calling createSubscription from subscription handler here.
      $subscription_id = $this->subscriptionHandler->createSubscription($sub_params, $this->acct);

    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error('Error creating new subscription: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Was not possible to create the membership.");
    }

    return $subscription_id;
  }

  public function reactivatePools($subscription) {
    if (empty($this->acct->id())) {
      throw new \Exception('getUserMemberships was called but user ID was not set. Use setAcctByUID() before calling this');
    }

    $classes = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'field_course' => $subscription->getCourseId(),
        'vid' => 'classes',
      ]);
    $required_update = FALSE;
    $pools_not_found = FALSE;
    foreach ($classes as $class) {
      $field_pooltype = $class->get('field_pool_type')->getValue();

      if ($field_pooltype[0]['target_id']) {
        $query = \Drupal::entityQuery('qwpool')
          ->condition('type', $field_pooltype[0]['target_id'])
          ->condition('user_id', $this->acct->id())
          ->condition('subscription_id', $subscription->id())
          ->condition('class', $class->id())
          ->condition('course', $subscription->getCourseId())
          ->sort('id', 'asc');
        $qwpools = $query->execute();
        // There should only be one per class. Activate the lowest ID one
        $pool_id = reset($qwpools);

        if (!empty($pool_id)) {
          //foreach($qwpools as $pool_id){
          $pool = \Drupal::entityTypeManager()
            ->getStorage('qwpool')
            ->load($pool_id);
          if (empty($pool->status->value)) {
            $pool->status = 1;
            $pool->save();
            $required_update = TRUE;
          }
        }
        else {
          // Pools do not exist for this user. Create them
          \Drupal::logger('MembershipHandler')
            ->error('reactivatePools() was unable to load a pool for ' . $this->acct->id() . ' for subscription ' . $subscription->id() . ', it will be created');
          $pools_not_found = TRUE;
        }
        //}
      }
    }

    // One or more pools were unable to be loaded. Recreate as needed. createPools will only create missing ones
    if ($pools_not_found) {
      $this->createPools($subscription);
    }

    // Refresh pools too, if an update was made
    if ($required_update) {
      $resultsService = \Drupal::service('qwizard.student_results_handler');
      $resultsService->rebuildStudentResults($this->acct, $subscription);
    }
  }

  /**
   * Creates pools for user based subscription.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createPools($subscription) {

    $classes = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties([
        'field_course' => $subscription->getCourseId(),
        'vid' => 'classes',
      ]);

    // Create a pool for each class.
    // @todo this should be a function of pools entity and should be called.
    foreach ($classes as $class) {
      // Check if the pool exists in D8 site. If not, create.
      $field_pooltype = $class->get('field_pool_type')->getValue();
      $qwpool_id = NULL;

      if ($field_pooltype[0]['target_id']) {
        $query = \Drupal::entityQuery('qwpool')
          ->condition('type', $field_pooltype[0]['target_id'])
          ->condition('user_id', $this->acct->id())
          ->condition('subscription_id', $subscription->id())
          ->condition('class', $class->id())
          ->condition('status', 1)
          ->condition('course', $subscription->getCourseId());
        $qwpools = $query->execute();
        // Should only be one.
        $qwpool_id = reset($qwpools);
      }

      if ($qwpool_id == NULL) {
        // Create the pool.
        // Load pool type.
        // @todo could load all these at once with classes.
        //   Most all are the same though.
        $pooltype_entity = \Drupal::entityTypeManager()
          ->getStorage('qwpool_type')
          ->load($field_pooltype[0]['target_id']);
        $properties = [
          'name' => $class->getName() . ' Pool',
          'type' => $field_pooltype[0]['target_id'],
          'created' => $subscription->getCreated(),
          'changed' => $_SERVER['REQUEST_TIME'],
          'user_id' => $this->acct->id(),
          'status' => $subscription->isActive(),
          'decrement' => ($pooltype_entity != NULL) ? $pooltype_entity->isDefaultDecrement() : NULL,
          'subscription_id' => $subscription->id(),
          'course' => $subscription->getCourseId(),
          'class' => $class->id(),
        ];
        $qwpool = QwPool::create($properties);
        $qwpool->save();
      }

    }

    // Refresh pools too
    $resultsService = \Drupal::service('qwizard.student_results_handler');
    $resultsService->rebuildStudentResults($this->acct, $subscription);
  }

  /**
   * Ends membership with the yesterday date. Does nothing if membership is
   * already expired
   *
   * @param int $membership_id
   *   The membership to end.
   *
   * @return bool
   *   TRUE if ends correctly.
   *
   * @throws Exception
   */
  public function endMembership(int $membership_id): bool {

    $membership = \Drupal::entityTypeManager()
      ->getStorage('subterm')
      ->load($membership_id);
    $existing_end_date = strtotime($membership->get('end')
      ->getValue()[0]['value']);
    // Only update the end date if it hasn't occurred yet
    if ($existing_end_date > strtotime('now')) {
      $membership->end = date('Y-m-d', strtotime(date('Y-m-d') . ' - 1 days'));
      try {
        $membership->save();
      }
      catch (\Throwable $e) {
        \Drupal::logger('qwizard')
          ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
        throw new Exception("Was not possible to end the membership.");
      }
    }
    return TRUE;
  }

  /**
   * Creates a new Membership with the data provided.
   *
   * @param array $params
   *   'user_id' int User id.
   *   'end' datetime Expiration date.
   *   'comment' string A comment.
   *   'subscription_id' int The subscription id.
   *
   * @return bool
   *   TRUE if created.
   *
   * @throws Exception
   */
  protected function createsNewMembership(array $params): bool {
    try {
      $membership = \Drupal::entityTypeManager()->getStorage('subterm');
      $subterm = $membership->create($params);
      $subterm->save();

      // Add user roles back if the user does not have them already
      $user = User::load($params['user_id']);
      $subscription = \Drupal::entityTypeManager()
        ->getStorage('subscription')
        ->load($params['subscription_id']);
      $course_id = $subscription->getCourseId();
      // @todo prem

      // Check if user has special_product role so it can be added back in.
      $this->special = in_array('special_product', $this->acct->getRoles());
      $this->addRolesToUser($course_id, $this->acct->id(), $subscription->getPremium(), $this->special);
    }
    catch (\Throwable $e) {
      \Drupal::logger('qwizard')
        ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Was not possible to create a membership with the data provided.");
    }

    return TRUE;

  }

  /**
   * Reactivate a suspended subscription.
   *
   * @param $subscription_id
   *
   * @throws Exception
   */
  public function reactivateSubscription($subscription_id) {
    $subscription = \Drupal::entityTypeManager()
      ->getStorage('subscription')
      ->load($subscription_id);
    if (empty($subscription->status->value)) {
      try {
        $subscription->status = 1;
        $subscription->save();

      }
      catch (\Throwable $e) {
        \Drupal::logger('qwizard')
          ->error($e->getMessage() . ' | ' . $e->getTraceAsString());
        throw new Exception($e->getMessage());
      }
    }

    // Also activate pools
    $this->reactivatePools($subscription);

    // Also add back roles.
    // Check if user has special_product role so it can be added back in.
    $this->special = in_array('special_product', $this->acct->getRoles());
    $this->addRolesToUser($subscription->course->target_id, $subscription->user_id->target_id, $subscription->getPremium(), $this->special);

    return $subscription;
  }

  /**
   * Unsubscribe to all subscription from a particular course.
   *
   * @param int $course
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function UnsubscribeToCourse(int $course): void {
    $subscriptions = \Drupal::entityQuery('subscription');
    $subscriptions->condition('course', $course);
    $subscriptions->condition('user_id', $this->acct->id());
    $subscriptions = $subscriptions->execute();
    foreach ($subscriptions as $subscription) {
      $sub = \Drupal::entityTypeManager()
        ->getStorage('subscription')
        ->load($subscription);
      $sub->status = 0;
      $sub->save();

      // Also end memberships tied to this course
      $membership = $this->getSubscriptionMembership($subscription);
      if (count($membership) > 0) {
        foreach ($membership as $m) {
          $this->endMembership($m);
        }
      }
    }

    // Also remove course roles, since this function expired ALL memberships in this course
    $this->removeCourseRoleFromUser($course);
  }

  /**
   * Given a course ID, remove course roles.
   *
   * @param int $course
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function removeCourseRoleFromUser(int $course): array {
    $roles_removed = [];
    $user = User::load($this->acct->id());
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $all_courses = $QwizardGeneral->getStatics('all_courses');

    if (!empty($all_courses[$course])) {
      $role = strtolower($all_courses[$course]);
      if ($user->hasRole($role)) {
        $user->removeRole($role);
        $roles_removed[] = $role;
      }
      if ($user->hasRole($role . '_premium')) {
        $user->removeRole($role . '_premium');
        $roles_removed[] = $role . '_premium';
      }
      // @todo ZUKU-1811 - Always keep special_product role.
      // if($user->hasRole('special_product')){
      //   $user->removeRole('special_product');
      //   $roles_removed[] = 'special_product';
      // }
    }
    if (!empty($roles_removed)) {
      $user->save();
    }

    return $roles_removed;
  }

  /**
   * @param      $course_id
   * @param null $subscription_id
   *
   * @return string
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLastSubscriptionExpirationDate($course_id, $subscription_id = NULL) {
    $expiration_date = NULL;

    $expiration_date = '';
    $storage = \Drupal::entityTypeManager()->getStorage('subterm');
    $query = \Drupal::entityQuery('subterm');
    $query->sort('end', 'DESC');
    $query->range(0, 1);

    // @todo should probably get the current course, but it isn't available on subterms
    //$query->condition('course_id', $course_id);

    // If subscription is available, get end date from it
    // If inactive, get membership with the latest end date in current course
    if (!empty($subscription_id)) {
      $query->condition('subscription_id', $subscription_id);
    }
    else {
      $query->condition('user_id', \Drupal::currentUser()->id());
    }

    $subterm_ids = $query->execute();
    if (!empty($subterm_ids)) {
      $subterm_id = reset($subterm_ids);
      $subterm = $storage->load($subterm_id);
      $expiration_date = strtotime($subterm->getEnd());
    }

    return $expiration_date;
  }

}
