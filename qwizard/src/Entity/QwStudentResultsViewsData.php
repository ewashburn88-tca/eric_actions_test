<?php

namespace Drupal\qwizard\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Student Results entities.
 */
class QwStudentResultsViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.
    return $data;
  }

}
