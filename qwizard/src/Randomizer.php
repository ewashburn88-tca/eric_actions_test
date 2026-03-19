<?php

namespace Drupal\qwizard;

/**
 * Class Randomizer.
 */
class Randomizer implements RandomizerInterface {

  /**
   * Constructs a new Randomizer object.
   */
  public function __construct() {

  }

  /**
   * Randomly shuffles associative array.
   *
   * @todo: This should be moved to a service
   *
   * @param $array
   *
   * @return bool
   */
  public static function shuffleAssoc(&$array) {
    if(!is_array($array) || empty($array)) {
      return false;
    }
    $tmp = array();
    foreach($array as $key => $value) {
      $tmp[] = array('k' => $key, 'v' => $value);
    }
    shuffle($tmp);
    $array = array();
    foreach($tmp as $entry) {
      $array[$entry['k']] = $entry['v'];
    }
    return true;
  }




}
