<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface QwizStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of Quiz revision IDs for a specific Quiz.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface $entity
   *   The Quiz entity.
   *
   * @return int[]
   *   Quiz revision IDs (in ascending order).
   */
  public function revisionIds(QwizInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as Quiz author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   Quiz revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface $entity
   *   The Quiz entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(QwizInterface $entity);

  /**
   * Unsets the language for all Quiz with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
