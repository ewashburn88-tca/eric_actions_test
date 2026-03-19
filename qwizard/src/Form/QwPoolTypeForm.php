<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QwPoolTypeForm.
 */
class QwPoolTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $qwpool_type = $this->entity;
    $content_entity_id = $qwpool_type->getEntityType()->getBundleOf();

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $qwpool_type->label(),
      '#description' => $this->t("Label for the Question Pool type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $qwpool_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\qwizard\Entity\QwPoolType::load',
      ],
      '#disabled' => !$qwpool_type->isNew(),
    ];

    $form['defaultDecrement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decrement'),
      '#default_value' => $qwpool_type->isDefaultDecrement(),
      '#description' => $this->t("Whether this pool will decrement correct questions."),
    ];

    $form['defaultDecrWrong'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('DecrWrong'),
      '#default_value' => $qwpool_type->isDefaultDecrWrong(),
      '#description' => $this->t("Whether this pool will decrement incorrect questions."),
    ];

    $form['defaultDecrSkipped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('DecrSkipped'),
      '#default_value' => $qwpool_type->isDefaultDecrSkipped(),
      '#description' => $this->t("Whether this pool will decrement skipped questions."),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $messenger = \Drupal::messenger();
    $qwpool_type = $this->getEntity();
    $status = $qwpool_type->save();

    switch ($status) {
      case SAVED_NEW:
        $messenger->addMessage($this->t('Created the %label Question Pool type.', [
          '%label' => $qwpool_type->label(),
        ]));
        break;

      default:
        $messenger->addMessage($this->t('Saved the %label Question Pool type.', [
          '%label' => $qwpool_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($qwpool_type->toUrl('collection'));
  }

}
