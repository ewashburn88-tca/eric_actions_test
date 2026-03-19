<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Question Pool entity.
 *
 * @see \Drupal\qwizard\Entity\QwPool.
 */
class QwPoolAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\qwizard\Entity\QwPoolInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished question pool entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published question pool entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit question pool entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete question pool entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add question pool entities');
  }

}
