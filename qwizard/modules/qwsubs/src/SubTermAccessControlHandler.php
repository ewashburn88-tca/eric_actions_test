<?php

namespace Drupal\qwsubs;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Subscription Term entity.
 *
 * @see \Drupal\qwsubs\Entity\SubTerm.
 */
class SubTermAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\qwsubs\Entity\SubTermInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view published subscription term entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit subscription term entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete subscription term entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add subscription term entities');
  }

}
