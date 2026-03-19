<?php

namespace Drupal\qwizard\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Quiz Results entities.
 */
class QwizResultViewsData extends EntityViewsData {

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
