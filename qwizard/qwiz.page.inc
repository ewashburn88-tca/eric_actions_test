<?php

/**
 * @file
 * Contains qwiz.page.inc.
 *
 * Page callback for Quiz entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Quiz templates.
 *
 * Default template: qwiz.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_qwiz(array &$variables) {
  // Fetch Qwiz Entity Object.
  $qwiz = $variables['elements']['#qwiz'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
