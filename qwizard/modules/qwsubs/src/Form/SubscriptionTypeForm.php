<?php

namespace Drupal\qwsubs\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SubscriptionTypeForm.
 */
class SubscriptionTypeForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $subscription_type = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $subscription_type->label(),
      '#description' => $this->t("Label for the Subscription type."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $subscription_type->id(),
      '#machine_name' => [
        'exists' => '\Drupal\qwsubs\Entity\SubscriptionType::load',
      ],
      '#disabled' => !$subscription_type->isNew(),
    ];

    /* You will need additional form elements for your custom properties. */

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $subscription_type = $this->entity;
    $status = $subscription_type->save();

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label Subscription type.', [
          '%label' => $subscription_type->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label Subscription type.', [
          '%label' => $subscription_type->label(),
        ]));
    }
    $form_state->setRedirectUrl($subscription_type->toUrl('collection'));
  }

}
