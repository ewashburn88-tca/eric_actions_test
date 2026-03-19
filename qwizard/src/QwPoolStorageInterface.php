<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface QwPoolStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Question Pool revision IDs for a specific Question Pool.
   *
   * @param \Drupal\qwizard\Entity\QwPoolInterface $entity
   *   The Question Pool entity.
   *
   * @return int[]
   *   Question Pool revision IDs (in ascending order).
   */
  public function revisionIds(QwPoolInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Question Pool author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Question Pool revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\qwizard\Entity\QwPoolInterface $entity
   *   The Question Pool entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(QwPoolInterface $entity);

  /**
   * Unsets the language for all Question Pool with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
