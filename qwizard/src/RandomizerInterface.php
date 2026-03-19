<?php

namespace Drupal\qwizard;

/**
 * Interface RandomizerInterface.
 */
interface RandomizerInterface {

  /**
   * Randomly shuffles associative array.
   *
   * @todo: This should be moved to a service
   *
   * @param $array
   *
   * @return bool
   */
  public static function shuffleAssoc(&$array);

}
