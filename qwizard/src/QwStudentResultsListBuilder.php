<?php

namespace Drupal\qwizard;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Student Results entities.
 *
 * @ingroup qwizard
 */
class QwStudentResultsListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Student Results ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\qwizard\Entity\QwStudentResults $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.qw_student_results.edit_form',
      ['qw_student_results' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
