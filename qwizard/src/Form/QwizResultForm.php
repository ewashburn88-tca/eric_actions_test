<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Quiz Results edit forms.
 *
 * @ingroup qwizard
 */
class QwizResultForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\qwizard\Entity\QwizResult */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;
    //$form["start"]["widget"][0]["value"]["#type"] = 'datetime';
    //$form["end"]["widget"][0]["value"]["#type"] = 'datetime';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label Quiz Results.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label Quiz Results.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.qwiz_result.canonical', ['qwiz_result' => $entity->id()]);
  }

}
