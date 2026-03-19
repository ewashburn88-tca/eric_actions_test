<?php

namespace Drupal\qwsubs;

use Drupal\qwizard\QwizardGeneral;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserInterface;

/**
 * Interface SubscriptionHandlerInterface.
 */
interface SubscriptionHandlerInterface {

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
   */
  public static function getCurrentSubscription($course, $uid = NULL, $type = NULL, bool $include_inactive = FALSE);


  /**
   * Returns user subscriptions.
   *
   * @param \Drupal\taxonomy\Entity\Term $course
   * @param null                         $uid
   * @param null                         $type
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getUserSubscriptions($uid = NULL, Term $course = NULL, $active = FALSE, $type = NULL);

  /**
   * Retrieves a subscription by UUID.
   *
   * @param $uuid
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getSubscriptionWithUuid($uuid);

  /**
   * Retrieves a subscription by UUID.
   *
   * @param $sid
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getSubscriptionWithSid($sid);

  /**
   * Creates a subscription and subterm from the parameters provided.
   *
   * @param array                  $params
   *     [
   *     'type' => <defaults to 'term'>,
   *     'name' => '',
   *     'status' =>  1 | 0,
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
  public static function createSubscription($params, $account = NULL);


}
