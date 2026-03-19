<?php


/**
 * @file
 * Contains qwsubs.inc.
 *      Generic helper functions for the qwsubs module.
 *
 */

/**
 * Formats string date or timestamp to ISO datetime.
 *
 * @param        $datetime
 * @param string $tz
 *
 * @return string
 * @throws \Exception
 */
function qwsubs_date_format_iso8601($datetime, $tz = 'UTC') {
  if($tz != 'UTC' && !in_array($tz, timezone_identifiers_list())) {
    throw new Exception('$tz must be valid PHP timezone string.');
  }
  $timezone = new \DateTimeZone($tz);
  if (is_int($datetime)) {
    // Assume timestamp.
    $date = new \DateTime('now', $timezone);
    $date->setTimestamp($datetime);
  }
  else {
    try {
      $date = new \DateTime($datetime, $timezone);
    }
    catch (Exception $e) {
      //display custom message
      echo $e->getMessage();
    }

  }
  return $date->format('c');
}
