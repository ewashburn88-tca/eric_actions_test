<?php

namespace Drupal\qwsubs\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Subscription entities.
 *
 * @ingroup qwsubs
 */
interface SubscriptionInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Subscription name.
   *
   * @return string
   *   Name of the Subscription.
   */
  public function getName();

  /**
   * Sets the Subscription name.
   *
   * @param string $name
   *   The Subscription name.
   *
   * @return \Drupal\qwsubs\Entity\SubscriptionInterface
   *   The called Subscription entity.
   */
  public function setName($name);

  /**
   * Gets the Subscription creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Subscription.
   */
  public function getCreated();

  /**
   * Sets the Subscription creation timestamp.
   *
   * @param int $timestamp
   *   The Subscription creation timestamp.
   *
   * @return \Drupal\qwsubs\Entity\SubscriptionInterface
   *   The called Subscription entity.
   */
  public function setCreated($timestamp);

  /**
   * Gets the Subscription creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Subscription.
   */
  public function getCreatedTime();

  /**
   * Sets the Subscription creation timestamp.
   *
   * @param int $timestamp
   *   The Subscription creation timestamp.
   *
   * @return \Drupal\qwsubs\Entity\SubscriptionInterface
   *   The called Subscription entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Subscription active status indicator.
   *
   * Unactive Subscription are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Subscription is active.
   */
  public function isActive();

  /**
   * Sets the active status of a Subscription.
   *
   * @param bool $active
   *   TRUE to set this Subscription to active, FALSE to set it to unactive.
   *
   * @return \Drupal\qwsubs\Entity\SubscriptionInterface
   *   The called Subscription entity.
   */
  public function setActive($active);

  /**
   * Returns the course term id.
   */
  public function getCourseId();

  /**
   * Sets the course term id.
   */
  public function setCourseId($tid);

  /**
   * Gets the Subscription revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the Subscription revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\qwsubs\Entity\SubscriptionInterface
   *   The called Subscription entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the Subscription revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the Subscription revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\qwsubs\Entity\SubscriptionInterface
   *   The called Subscription entity.
   */
  public function setRevisionUserId($uid);

  /**
   * Whether a user has a certain role.
   *
   * @param string $rid
   *   The role ID to check.
   *
   * @return bool
   *   Returns TRUE if the user has the role, otherwise FALSE.
   */
  public function hasRole($rid);

  /**
   * Add a role to a user.
   *
   * @param string $rid
   *   The role ID to add.
   */
  public function addRole($rid);

  /**
   * Remove a role from a user.
   *
   * @param string $rid
   *   The role ID to remove.
   */
  public function removeRole($rid);

  /**
   * Gets the data field as native json or as php array.
   *
   * @param bool $as_json
   *
   * @return array|Json
   */
  public function getDataArray($as_json = FALSE);

  /**
   * Activates the subscription if there is a current subterm.
   *
   * @return $this|bool|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function activateSubscription();

  /**
   * Activates the subscription, creating term.
   *
   * @deprecated Subterm should be created with subscription, then activated.
   * @see createSubscription()
   * @see activateSubscription()
   *
   * @param $end
   *
   * @return mixed|void
   * @throws \Exception
   */
  public function activateSubscriptionCreateTerm($end);

  /**
   * DeActivates the subscription.
   */
  public function deActivateSubscription();

  /**
   * Cancels the subscription.
   */
  public function cancelSubscription();

  /**
   * Gets the current term of the subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCurrentSubTerm();

  /**
   * Gets the current datetime of current subterm end.
   *
   * @param bool $as_datetime
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCurrentTermEnd($as_datetime = FALSE);

  /**
   * Get the most recent SubTerm, current or not.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLastSubTerm();

  /**
   * Gets the subterms for this subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubTerms();

}
