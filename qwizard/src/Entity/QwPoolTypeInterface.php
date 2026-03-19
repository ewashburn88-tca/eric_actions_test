<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;

/**
 * Provides an interface for defining Question Pool type entities.
 */
interface QwPoolTypeInterface extends ConfigEntityInterface {

  // Add get/set methods for your configuration properties here.

  /**
   * Determines if defaultDecrement is set.
   *
   * @return bool
   */
  public function isDefaultDecrement(): bool ;

  /**
   * Sets the defaultDecrement.
   *
   * @param bool $defaultDecrement
   */
  public function setDefaultDecrement(bool $defaultDecrement): void ;

  /**
   * Determines if defaultDecrement is set.
   *
   * @return bool
   */
  public function isDefaultDecrWrong(): bool ;

  /**
   * Sets the defaultDecrement.
   *
   * @param bool $defaultDecrement
   */
  public function setDefaultDecrWrong(bool $defaultDecrWrong): void ;

  /**
   * Determines if defaultDecrement is set.
   *
   * @return bool
   */
  public function isDefaultDecrSkipped(): bool ;

  /**
   * Sets the defaultDecrement.
   *
   * @param bool $defaultDecrement
   */
  public function setDefaultDecrSkipped(bool $defaultDecrSkipped): void ;

}
