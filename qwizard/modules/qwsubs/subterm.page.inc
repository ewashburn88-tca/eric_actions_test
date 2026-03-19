<?php

/**
 * @file
 * Contains subterm.page.inc.
 *
 * Page callback for Subscription Term entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Subscription Term templates.
 *
 * Default template: subterm.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_subterm(array &$variables) {
  // Fetch SubTerm Entity Object.
  $subterm = $variables['elements']['#subterm'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
