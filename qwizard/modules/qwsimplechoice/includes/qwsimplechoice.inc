<?php

/**
 * @file
 * Simple Choice helper functions.
 */

/**
 * Randomly shuffles associative array.
 *
 * Deprecated: use Randomizer service.
 *
 * @todo: This should be moved to a service
 *
 * @param $array
 *
 * @return bool
 */
function qwsimplechoice_shuffle_assoc(&$array) {
  return \Drupal\qwizard\Randomizer::shuffleAssoc($array);
}
