<?php

namespace Drupal\qwsubs\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Provides an interface for defining Subscription Term entities.
 *
 * @ingroup qwsubs
 */
interface SubTermInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Gets the Subscription Term name.
   *
   * @return string
   *   Name of the Subscription Term.
   */
  public function getComment();

  /**
   * Sets the Subscription Term name.
   *
   * @param string $name
   *   The Subscription Term name.
   *
   * @return \Drupal\qwsubs\Entity\SubTermInterface
   *   The called Subscription Term entity.
   */
  public function setComment($name);

  /**
   * Gets the Subscription Term creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Subscription Term.
   */
  public function getCreatedTime();

  /**
   * Sets the Subscription Term creation timestamp.
   *
   * @param int $timestamp
   *   The Subscription Term creation timestamp.
   *
   * @return \Drupal\qwsubs\Entity\SubTermInterface
   *   The called Subscription Term entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Determines if this is a current subterms.
   *
   * @return bool
   */
  public function isCurrent();

  /**
   * Gets the end date of the subterm.
   *
   * @param bool $as_datetime
   *
   * @return \DateTime|mixed
   */
  public function getEnd($as_datetime = FALSE);

  /**
   * Sets the end date of the subterm.
   *
   * @param \DateTime|string $end
   *
   * @return $this|\Drupal\qwsubs\Entity\SubTerm
   * @throws \Exception
   */
  public function setEnd($end);

  /**
   * Gets the start date of the subterm.
   *
   * @param bool $as_datetime
   *
   * @return \DateTime|mixed
   */
  public function getStart($as_datetime = FALSE);

  /**
   * Sets the start date of the subterm.
   *
   * @param \DateTime|string $start
   *
   * @return $this|\Drupal\qwsubs\Entity\SubTerm
   * @throws \Exception
   */
  public function setStart($start);

  /**
   * Gets the interval to end of subterm, may be negative if expired.
   *
   * @return object DateInterval Object
   */
  public function getRemaining();

  /**
   * Closes out a subterm.
   *
   * @param string $reason
   * @param null   $end
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cancelSubTerm($reason = '', $end = NULL);

  /**
   * Gets the parent subscription id.
   *
   * @return mixed
   */
  public function getSubscriptionId();

  /**
   * Gets the fully loaded parent subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubscription();

  /**
   * Retrieves all subscription term ids for a given subscription.
   *
   * @param $subscription_id
   *
   * @return array
   */
  public static function getSubTerms($subscription_id);

  /**
   * Gets a subterm by its UUID.
   *
   * @param $uuid
   *
   * @return \Drupal\qwsubs\Entity\SubTerm | NULL
   */
  public static function getSubTermByUuid($uuid);

}
