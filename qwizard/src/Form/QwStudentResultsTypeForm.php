<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QwStudentResultsTypeForm.
 */
class QwStudentResultsTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $qw_student_results_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $qw_student_results_type->label(),
      '#description' => $this->t("Label for the Qw student results type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $qw_student_results_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\qwizard\Entity\QwStudentResultsType::load',
      ],
      '#disabled' => !$qw_student_results_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $qw_student_results_type = $this->entity;
    $status = $qw_student_results_type->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Qw student results type.', [
          '%label' => $qw_student_results_type->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Qw student results type.', [
          '%label' => $qw_student_results_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($qw_student_results_type->toUrl('collection'));
  }

}
