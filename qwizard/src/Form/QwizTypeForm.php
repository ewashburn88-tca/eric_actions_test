<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QwizTypeForm.
 */
class QwizTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $qwiz_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $qwiz_type->label(),
      '#description' => $this->t("Label for the Quiz type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $qwiz_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\qwizard\Entity\QwizType::load',
      ],
      '#disabled' => !$qwiz_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $qwiz_type = $this->entity;
    $status = $qwiz_type->save();

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label Quiz type.', [
          '%label' => $qwiz_type->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label Quiz type.', [
          '%label' => $qwiz_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($qwiz_type->toUrl('collection'));
  }

}
