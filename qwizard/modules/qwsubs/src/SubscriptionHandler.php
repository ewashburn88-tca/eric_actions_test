<?php

namespace Drupal\qwsubs;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\qwizard\ClassesHandler;
use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\Entity\QwPool;
use Drupal\qwizard\MembershipHandler;
use Drupal\qwizard\QwizardGeneral;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\zukufe\StatusUserHandler;

/**
 * Class SubscriptionHandler.
 *
 * @todo Rewrite as a service.
 */
class SubscriptionHandler implements SubscriptionHandlerInterface {

  /**
   * Constructs a new SubscriptionHandler object.
   */
  public function __construct() {
  }


  /**
   * @param ContainerInterface $container
   * @return SubscriptionHandler
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container): SubscriptionHandler
  {
    return new static(
      //$container->get();
    );
  }

  /**
   * Returns current user subscription.
   *
   * @param $course
   * @param $uid
   * @param $type
   * @param bool $include_inactive
   *
   * @return \Drupal\Core\Entity\EntityInterface|mixed|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo type parameter on subscriptions can only be "term" currently from DB
   */
  public static function getCurrentSubscription($course, $uid = NULL, $type = NULL, bool $include_inactive = FALSE) {
    if(empty($course)){
      return null;
    }
    if(is_int($course)){
      $course = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($course);
    }

    if ($uid == NULL) {
      $uid     = \Drupal::currentUser()->id();
    }
    $query = \Drupal::entityQuery('subscription')
      ->condition('user_id', $uid)
      ->condition('course', $course->id());
    if(!$include_inactive){
      $query->condition('status', 1);
    }
    // The only type is 'term' currently, this may be updated in the future
    // Ignore type
    if ($type) {
      //$query->condition('type', $type);
    }
    $query->sort('status', 'DESC');
    $query->sort('changed', 'DESC');
    $sids = $query->execute();

    // Should only be one.
    if(!$include_inactive && count($sids) > 1) {
      \Drupal::logger('subscription')->notice('getCurrentSubscription returned more than 1 item for user ' . $uid . ' and course ' . $course->label().': Sub IDs are '.json_encode($sids));
    }
    $sid       = reset($sids);
    $sub_store = \Drupal::entityTypeManager()->getStorage('subscription');

    return $sid ? $sub_store->load($sid) : NULL;
  }

  /**
   * Returns user subscriptions.
   *
   * @param null $uid
   * @param Term|null $course
   * @param bool $active
   * @param null $type
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getUserSubscriptions($uid = NULL, Term $course = NULL, $active = FALSE, $type = NULL) {
    if ($uid == NULL) {
      $account = \Drupal::currentUser();
      $uid     = $account->id();
    }
    $query = \Drupal::entityQuery('subscription')
      ->condition('user_id', $uid);
    if ($course instanceof Term) {
      $query->condition('course', $course->Id());
    }
    if ($type) {
      $query->condition('type', $type);
    }
    if ($active) {
      $query->condition('status', 1);
    }
    $query->sort('status', 'DESC');
    $query->sort('id', 'DESC');
    $sids      = $query->execute();
    $sub_store = \Drupal::entityTypeManager()->getStorage('subscription');
    return $sub_store->loadMultiple($sids);
  }

  /**
   * Retrieves a subscription by UUID.
   *
   * @param $uuid
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getSubscriptionWithUuid($uuid) {
    $query        = \Drupal::entityQuery('subscription')
      ->condition('uuid', $uuid);
    $sids         = $query->execute();
    $sid          = reset($sids);
    $sub_store    = \Drupal::entityTypeManager()->getStorage('subscription');
    $subscription = $sub_store->load($sid);
    return $subscription;
  }

  /**
   * Retrieves a subscription by UUID.
   *
   * @param $sid
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getSubscriptionWithSid($sid) {
    $query        = \Drupal::entityQuery('subscription')
      ->condition('subscription_id', $sid);
    $sids         = $query->execute();
    $sid          = reset($sids);
    $sub_store    = \Drupal::entityTypeManager()->getStorage('subscription');
    $subscription = $sub_store->load($sid);
    return $subscription;
  }

  /**
   * Creates a subscription and subterm from the parameters provided.
   *
   * @param array                  $params
   *     [
   *     'type' => <defaults to 'term'>,
   *     'name' => '',
   *     'active' =>  1 | 0,
   *     'max_term' => <#days | default 365>
   *     'course' => <taxonomy_term id>,
   *     'data' => Json | array
   *     'start' => DateTime | string date | unix timestamp
   *     'end' => DateTime | string date | unix timestamp
   *     'comment' => '',
   *     'roles' => array(<role ids>)
   *     ]   *
   * @param null|UserInterface|int $account
   *     If not provided or is NULL, will user current user account.
   *
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function createSubscription($params, $account = NULL) {
    // First handle user account.
    if (!$account instanceof User) {
      $account = QwizardGeneral::getAccountInterface($account);
      if (empty($account)) {
        throw new EntityStorageException('Can not create a subscription without a valid user account.');
      }
    }

    // Adjust params as needed
    $course = $params['course'] ?? \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
    $params['type'] = ($params['type']) ?: 'term';
    $params['comment'] = (isset($params['comment'])) ? $params['comment'] : '';

    if (!isset($params['name'])) {
      $params['name'] = $account->getAccountName() . '-' . empty($course) ? '' : $course->label();
    }

    if (isset($params['start'])) {
      $start = QwizardGeneral::getDateTime($params['start']);
      $now   =  QwizardGeneral::getDateTime('now');

      if ($start->getTimestamp() <= $now->getTimestamp()) {
        // @todo double check active logic. it used to require $params['active'] to be not set
        $params['active'] = "1";
        $params['start']  = $start->format('Y-m-d');
      }
    }
    $end = $params['end'] ?: date('Y-m-d', strtotime(date('Y-m-d', strtotime($params['start'])) . ' + ' . $params['max_term'] . ' days'));

    // Create Subscription
    $sub_storage               = \Drupal::entityTypeManager()
      ->getStorage('subscription');
    $is_premium_sub = 0;
    if(!empty($params['premium'])){
      $is_premium_sub = 1;
    }
    $sub                       = $sub_storage->create([
      'langcode' => 'en',
      'type'     => $params['type'],
      'user_id'  => $account->id(),
      'name'     => $params['name'],
      'status'   => $params['active'],
      'start'    => $params['start'],
      'course'   => $params['course'],
      'max_term' => $params['max_term'],
      'premium' => $is_premium_sub
    ]);

    // Append roles.
    if (!empty($params['roles'])) {
      foreach ($params['roles'] as $role) {
        $sub->roles->appendItem($role);
      }
    }

    // Update user roles and activate account
    $roles = self::getCourseRoles((int) $params['course']);

    if (empty($params['premium'])) {
      // Remove premium roles from user roles if premium is set to false
      // @todo this looks like hardcoded premium data
      $prem_course = ($params['course'] == 200) ? 'navle_premium' : 'bcse_premium';
      if (($key = array_search($prem_course, $roles)) !== FALSE) {
        unset($roles[$key]);
      }
    }
    if ($params['special']) {
      $roles[] = 'special_product';
    }
    foreach ($roles as $role) {
      $sub->roles->appendItem($role);
      if(!$account->hasRole($role)) {
        $account->addRole($role);
      }
    }

    // @todo should user update only be done once we know the other saving works?

    // Activate the account since they now have a subscription
    if (!$account->isActive()) {
      $account->activate();
    }
    // Save account & Subscription.
    try {
      $account->save();
    }
    catch (\Throwable $e) {
      \Drupal::logger('subscription')->error('Error saving user account: ' . $account->id() . ' - ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
      throw new Exception("Was not possible to create the subscription.");
    }
    try {
      $sub->save();
    }
    catch (\Throwable $e) {
      \Drupal::logger('subscription')->error('Error creating new subscription: '.$e->getMessage().' | '.$e->getTraceAsString());
      throw new Exception("Was not possible to create the subscription.");
    }


    // Create Membership SubTerm
    $subterm_storage = \Drupal::entityTypeManager()->getStorage('subterm');
    $comment = '';
    if(!empty($params['comment'])){
      $comment = $params['comment'];
    }
    else {
      // Autogenerate SubTerm/Membership comment if needed
      // @todo use DI for $membershipHandler call here, WITHOUT causing a circular reference since this class is used by qwizard.membership already
      $membershipHandler = \Drupal::service('qwizard.membership');
      $membershipHandler->setAcctByUID($account->id());
      if($membershipHandler->isUserSubscribedToCourse($course, true)){
        $comment = 'Renew membership';
      }
    }

    $subterm         = $subterm_storage->create([
      'user_id'         => $account->id(),
      'end'             => $end,
      'start'           => $params['start'],
      'comment'         => $comment,
      'subscription_id' => $sub->id(),
    ]);

    try {
      $subterm->save();
    }
    catch (\Throwable $e) {
      \Drupal::logger('subscription')->error('Error saving subterm for new subscription: '.$e->getMessage().' | '.$e->getTraceAsString());
      throw new Exception("Was not possible to create the subscription.");
    }



    // Create pools for each class in the course
    $classes = ClassesHandler::getClassesInCourse($params['course']);
    foreach ($classes as $cid) {
      $class = Term::load($cid);
      $pool  = QwPool::create([
        'type'            => $class->field_pool_type->target_id,
        'user_id'         => $account->id(),
        'subscription_id' => $sub->id(),
        'name'            => $class->getName(),
        'status'          => $params['active'],
        'course'          => $params['course'],
        'class'           => $class->id(),
      ]);
      $pool->save();
    }

    // Refresh pools too
    $resultsService = \Drupal::service('qwizard.student_results_handler');
    $resultsService->rebuildStudentResults($account, $sub);

    return $sub->id();
  }


  /**
   * Gets roles for a course. Can be useful elsewhere
   * @param int $course
   * @return array
   */
  public static function getCourseRoles(int $course): array {
    $course  = strtolower(Term::load($course)->getName());
    $roles   = Role::loadMultiple();
    $matches = [];
    foreach ($roles as $role => $data) {
      if (strpos(strtolower($role), $course) !== FALSE) {
        array_push($matches, $data->id());
      }
    }
    return $matches;
  }

  /**
   * Cancel a subscription.
   *
   * @param $subscription_id
   *
   * @throws \Exception
   */
  public static function cancelSubscription($subscription_id) {
    try {
      $subscription         = \Drupal::entityTypeManager()
        ->getStorage('subscription')
        ->load($subscription_id);
      $subscription->status = 0;
      $subscription->save();

      //Pause all pools.
      $query = \Drupal::entityQuery('qwpool')
        ->condition('subscription_id', $subscription_id);
      $pids  = $query->execute();

      $storage = \Drupal::entityTypeManager()->getStorage('qwpool');
      $pools   = $storage->loadMultiple($pids);
      foreach ($pools as $pool) {
        $pool->setStatus(0);
        $pool->save();
      }
      // pause all memberships
      // @todo use DI for $membershipHandler call here, WITHOUT causing a circular reference since this class is used by qwizard.membership already
      $membershipHandler = \Drupal::service('qwizard.membership');
      $membershipHandler->setAcctByUID($subscription->user_id->target_id);
      $memberships   = $membershipHandler->getSubscriptionMembership($subscription_id);
      foreach ($memberships as $m) {
        $mem      = \Drupal::entityTypeManager()
          ->getStorage('subterm')
          ->load($m);
        $mem->end = date('Y-m-d', strtotime(date('Y-m-d') . '- 1 day'));
        $mem->save();
      }

      // Remove roles
      $membershipHandler->removeCourseRoleFromUser($subscription->course->target_id);

    }
    catch (\Throwable $e) {
      throw new \Exception($e->getMessage());
    }
  }


  /**
   * Get a user, get their current sub, get its start date. If before 1-1-24, allow them to see flashcards.
   * ZUKU-1392
   * @param $course_id
   * @return int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function shouldShowFlashcards($course_id){
    $timestamp_cutoff_for_flashcards = 1704070801;//1-1-2024
    $should_show = 0;
    $course_name = 'NAVLE';
    if($course_id == 201){
      $course_name = 'BCSE';
    }
    if($course_id == 202){
      $course_name = 'VTNE';
    }

    // Allow for role overrides
    $user = User::load(\Drupal::currentUser()->id());
    if($user->hasRole('show_vgb')){
      return 1;
    }

    $currentSubscription = $this->getCurrentSubscription((int) $course_id, \Drupal::currentUser()->id());
    if(empty($currentSubscription)) return $should_show;

    $data = StatusUserHandler::getData(\Drupal::currentUser()->id(), $course_name);
    if(empty($data['subscription']['subscription_created'])) return $should_show;
    $course_start = strtotime($data['subscription']['subscription_created']);

    if($course_start < $timestamp_cutoff_for_flashcards){
      $should_show = 1;
    }

    return $should_show;
  }

}
