<?php

namespace Drupal\qwschools;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the School entity.
 *
 * @see \Drupal\qwschools\Entity\School.
 */
class SchoolAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\qwschools\Entity\SchoolInterface $entity */

    switch ($operation) {

      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished school entities');
        }

        return AccessResult::allowed();

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit school entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete school entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add school entities');
  }

}
