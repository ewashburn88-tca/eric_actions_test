<?php

namespace Drupal\qwschools;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\qwschools\Entity\SchoolInterface;

/**
 * Defines the storage handler class for School entities.
 *
 * This extends the base storage class, adding required special handling for
 * School entities.
 *
 * @ingroup qwschools
 */
class SchoolStorage extends SqlContentEntityStorage implements SchoolStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(SchoolInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {school_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {school_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(SchoolInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {school_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('school_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
