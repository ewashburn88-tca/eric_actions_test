<?php

namespace Drupal\qwmarked;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the marked_question entity type.
 */
class MarkedQuestionAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view marked_question');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit marked_question', 'administer marked_question'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete marked_question', 'administer marked_question'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create marked_question', 'administer marked_question'], 'OR');
  }

}
