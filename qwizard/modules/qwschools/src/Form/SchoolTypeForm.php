<?php

namespace Drupal\qwschools\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SchoolTypeForm.
 */
class SchoolTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $school_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $school_type->label(),
      '#description' => $this->t("Label for the School type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $school_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\qwschools\Entity\SchoolType::load',
      ],
      '#disabled' => !$school_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $school_type = $this->entity;
    $status = $school_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label School type.', [
          '%label' => $school_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label School type.', [
          '%label' => $school_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($school_type->toUrl('collection'));
  }

}
