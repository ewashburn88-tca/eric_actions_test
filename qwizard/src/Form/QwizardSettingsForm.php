<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QwizardSettingsForm.
 */
class QwizardSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'qwizard.qwizardsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwizard_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('qwizard.qwizardsettings');

    $form['welcome_message'] = [
      '#markup' => '<div>Welcome to the Quiz Wizard Admin Area</div>',
    ];

    $contentTypes = \Drupal::service('entity_type.manager')->getStorage('node_type')->loadMultiple();

    $contentTypesList = [];
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
    }

    $default = $config->get('question_types');
    $form['question_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this
        ->t('Select Question Types'),
      '#description' => $this->t('Select node types that are questions for the Quiz Wizard module.'),
      '#default_value' => $default,
      '#options' => $contentTypesList,
    ];

    $form['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Mode'),
      '#description' => $this->t('Puts Quiz Wizard into debug mode for additional logging.'),
      '#default_value' => !empty($config->get('debug_mode')) ? $config->get('debug_mode') : FALSE,
    ];

    $form['rebuild_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rebuild pools on login'),
      '#description' => $this->t('If checked a user\'s pools and results will be rebuilt at login.'),
      '#default_value' => !empty($config->get('rebuild_mode'))? $config->get('rebuild_mode') : FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $qtypes = array_keys(array_filter($form_state->getValue('question_types')));

    $this->config('qwizard.qwizardsettings')
      ->set('question_types', $qtypes)
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->set('rebuild_mode', $form_state->getValue('rebuild_mode'))
      ->save();
  }

}
