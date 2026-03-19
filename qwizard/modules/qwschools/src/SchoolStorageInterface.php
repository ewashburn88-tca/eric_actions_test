<?php

namespace Drupal\qwschools;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface SchoolStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of School revision IDs for a specific School.
   *
   * @param \Drupal\qwschools\Entity\SchoolInterface $entity
   *   The School entity.
   *
   * @return int[]
   *   School revision IDs (in ascending order).
   */
  public function revisionIds(SchoolInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as School author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   School revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\qwschools\Entity\SchoolInterface $entity
   *   The School entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(SchoolInterface $entity);

  /**
   * Unsets the language for all School with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
