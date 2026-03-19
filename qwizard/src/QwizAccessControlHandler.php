<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Quiz entity.
 *
 * @see \Drupal\qwizard\Entity\Qwiz.
 */
class QwizAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\qwizard\Entity\QwizInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished quiz entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published quiz entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit quiz entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete quiz entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add quiz entities');
  }

}
