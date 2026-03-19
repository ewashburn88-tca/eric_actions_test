<?php

/**
 * @file
 * Contains qw_student_results.page.inc.
 *
 * Page callback for Student Results entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Student Results templates.
 *
 * Default template: qw_student_results.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_qw_student_results(array &$variables) {
  // Fetch QwStudentResults Entity Object.
  $qw_student_results = $variables['elements']['#qw_student_results'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
