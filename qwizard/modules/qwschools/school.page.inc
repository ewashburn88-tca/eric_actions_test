<?php

/**
 * @file
 * Contains school.page.inc.
 *
 * Page callback for School entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for School templates.
 *
 * Default template: school.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_school(array &$variables) {
  // Fetch School Entity Object.
  $school = $variables['elements']['#school'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
