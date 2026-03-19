<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Handles group operations.
 */
class GroupController extends ControllerBase {

  /**
   * Archive the group.
   */
  public function archiveGroup($group) {
    $group_term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($group);
    $group_term->set('field_archived', 1)->save();
    $this->messenger()->addStatus($this->t('The group %label has been archived successfully.', [
      '%label' => $group_term->getName(),
    ]));
    return $this->redirect('qwreporting.hompage');
  }

  /**
   * Restore the group.
   */
  public function restoreGroup($group) {
    $group_term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($group);
    $group_term->set('field_archived', 0)->save();
    $this->messenger()->addStatus($this->t('The group %label has been restored successfully.', [
      '%label' => $group_term->getName(),
    ]));
    return $this->redirect('qwreporting.hompage');
  }

}
