<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Quiz Results entities.
 *
 * @ingroup qwizard
 */
class QwizResultListBuilder extends EntityListBuilder {


  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Quiz Results ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\qwizard\Entity\QwizResult */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.qwiz_result.edit_form',
      ['qwiz_result' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
