<?php

/**
 * @file
 * Contains qwiz_result.page.inc.
 *
 * Page callback for Quiz Results entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Quiz Results templates.
 *
 * Default template: qwiz_result.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_qwiz_result(array &$variables) {
  // Fetch QwizResult Entity Object.
  $qwiz_result = $variables['elements']['#qwiz_result'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
