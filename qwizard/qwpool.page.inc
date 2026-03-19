<?php

/**
 * @file
 * Contains qwpool.page.inc.
 *
 * Page callback for Question Pool entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Question Pool templates.
 *
 * Default template: qwpool.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_qwpool(array &$variables) {
  // Fetch QwPool Entity Object.
  $qwpool = $variables['elements']['#qwpool'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
