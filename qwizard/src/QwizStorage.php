<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\qwizard\Entity\QwizInterface;

/**
 * Defines the storage handler class for Quiz entities.
 *
 * This extends the base storage class, adding required special handling for
 * Quiz entities.
 *
 * @ingroup qwizard
 */
class QwizStorage extends SqlContentEntityStorage implements QwizStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(QwizInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {qwiz_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {qwiz_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(QwizInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {qwiz_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('qwiz_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
