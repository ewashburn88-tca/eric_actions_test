<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\qwizard\Entity\QwPoolInterface;

/**
 * Defines the storage handler class for Question Pool entities.
 *
 * This extends the base storage class, adding required special handling for
 * Question Pool entities.
 *
 * @ingroup qwizard
 */
class QwPoolStorage extends SqlContentEntityStorage implements QwPoolStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(QwPoolInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {qwpool_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {qwpool_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(QwPoolInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {qwpool_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('qwpool_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
