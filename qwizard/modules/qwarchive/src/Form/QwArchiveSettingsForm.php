<?php

namespace Drupal\qwarchive\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure QW archive settings.
 */
class QwArchiveSettingsForm extends ConfigFormBase {

  /**
   * The config object identifier.
   */
  const SETTINGS = 'qwarchive.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwarchive_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['inactive_threshold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Inactive days threshold'),
      '#description' => $this->t('Enter a time threshold (e.g. "2 years ago", "12/31/2023"). Users who have not logged in by this time will be identified.'),
      '#default_value' => $config->get('inactive_threshold') ?? '12/31/2023',
      '#required' => TRUE,
    ];

    $form['enable_cron_archival'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Automated Archival'),
      '#description' => $this->t('If checked, inactive users will be automatically archived during cron runs.'),
      '#default_value' => $config->get('enable_cron_archival') ?? FALSE,
    ];

    $form['cron_batch_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Cron Batch Size'),
      '#description' => $this->t('The number of users to process per cron run. Lower this number if you experience timeouts.'),
      '#default_value' => $config->get('cron_batch_size') ?? 50,
      '#min' => 1,
      '#max' => 500,
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="enable_cron_archival"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()->getEditable(static::SETTINGS);

    $config->set('inactive_threshold', $form_state->getValue('inactive_threshold'));
    $config->set('enable_cron_archival', $form_state->getValue('enable_cron_archival'));
    $config->set('cron_batch_size', $form_state->getValue('cron_batch_size'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
