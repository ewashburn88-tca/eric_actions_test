<?php

namespace Drupal\qwizard;

/**
 * Interface MembershipHandlerInterface.
 */
interface MembershipHandlerInterface {

  /**
   * Creates a membership for a new user.
   */
  public function createNewSubscription();

  /**
   * Extends a membership by a paid extension.
   */
  public function renewMembership(int $subscription_id, int $membership = NULL, int $days = NULL);

  /**
   * Creates a copy of the last membership.
   * @param int $subscription_id
   * @return bool
   */
  public function createCopyOfLastMembership(int $subscription_id) : bool;
}
