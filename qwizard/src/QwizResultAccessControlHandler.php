<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Quiz Results entity.
 *
 * @see \Drupal\qwizard\Entity\QwizResult.
 */
class QwizResultAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\qwizard\Entity\QwizResultInterface $entity */
    switch ($operation) {
      case 'view':
        /*
         * @todo
         * Removing the unpublished check, it throws an error of Call to a member function getFieldStorageDefinition() on null in Drupal\Core\Entity\ContentEntityBase->getEntityKey()
         * Would likely require changing the base entity to support this check https://www.drupal.org/project/drupal/issues/2716075
         * Given that these permissions are only for admins, should be fine.
         * if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished quiz results entities');
        }*/
        return AccessResult::allowedIfHasPermission($account, 'view published quiz results entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit quiz results entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete quiz results entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add quiz results entities');
  }

}
