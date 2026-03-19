<?php

namespace Drupal\qwarchive\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\qwarchive\Plugin\QwArchiveStoragePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure QW archive storage settings.
 */
class QwArchiveStorageSettingsForm extends ConfigFormBase {

  /**
   * The qwarchive storage plugin manager.
   */
  protected QwArchiveStoragePluginManager $storagePluginManager;

  /**
   * The config object identifier.
   */
  const SETTINGS = 'qwarchive.settings';

  /**
   * Constructs a QwArchiveStorageSettingsForm object.
   *
   * @param \Drupal\qwarchive\Plugin\QwArchiveStoragePluginManager $storage_plugin_manager
   *   The storage plugin manager.
   */
  public function __construct(QwArchiveStoragePluginManager $storage_plugin_manager) {
    $this->storagePluginManager = $storage_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.qwarchive_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwarchive_storage_settings';
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
    $selected_plugin = $form_state->getValue('storage_plugin') ?? $config->get('storage_plugin') ?? NULL;
    $plugin_config = $config->get('plugin_configuration') ?? [];

    $form['storage_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Storage method'),
      '#description' => $this->t('Select the storage method to use for storing JSON files.'),
      '#options' => $this->getStoragePluginOptions(),
      '#default_value' => $selected_plugin,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::pluginConfigAjaxCallback',
        'wrapper' => 'plugin-configuration-wrapper',
      ],
      '#empty_option' => $this->t('- Select a storage method -'),
    ];

    // Container for plugin-specific configuration.
    $form['plugin_configuration'] = [
      '#type' => 'container',
      '#prefix' => '<div id="plugin-configuration-wrapper">',
      '#suffix' => '</div>',
      '#tree' => TRUE,
    ];

    // Build plugin configuration form if a plugin is selected.
    if (!empty($selected_plugin)) {
      try {
        $configuration = $plugin_config[$selected_plugin] ?? [];
        $plugin_instance = $this->storagePluginManager->createInstance($selected_plugin, $configuration);

        $form['plugin_configuration']['description'] = [
          '#type' => 'item',
          '#markup' => '<p><strong>' . $plugin_instance->getLabel() . ':</strong> ' . $plugin_instance->getDescription() . '</p>',
        ];

        $subform_state = SubformState::createForSubform($form['plugin_configuration'], $form, $form_state);
        $form['plugin_configuration'] += $plugin_instance->buildConfigurationForm($form['plugin_configuration'], $subform_state);
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error loading plugin configuration: @message', ['@message' => $e->getMessage()]));
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for plugin configuration.
   */
  public function pluginConfigAjaxCallback(array &$form, FormStateInterface $form_state) {
    return $form['plugin_configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $selected_plugin = $form_state->getValue('storage_plugin');
    if (!empty($selected_plugin)) {
      try {
        $config = $this->config(static::SETTINGS);
        $plugin_config = $config->get('plugin_configuration') ?? [];
        $configuration = $plugin_config[$selected_plugin] ?? [];

        $plugin_instance = $this->storagePluginManager->createInstance($selected_plugin, $configuration);
        $subform_state = SubformState::createForSubform($form['plugin_configuration'], $form, $form_state);
        $plugin_instance->validateConfigurationForm($form['plugin_configuration'], $subform_state);
      }
      catch (\Exception $e) {
        $form_state->setErrorByName('storage_plugin', $this->t('Error validating plugin configuration: @message', ['@message' => $e->getMessage()]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $selected_plugin = $form_state->getValue('storage_plugin');
    $config = $this->configFactory()->getEditable(static::SETTINGS);

    $config->set('storage_plugin', $selected_plugin);

    if (!empty($selected_plugin)) {
      try {
        $existing_config = $config->get('plugin_configuration') ?? [];
        $configuration = $existing_config[$selected_plugin] ?? [];

        $plugin_instance = $this->storagePluginManager->createInstance($selected_plugin, $configuration);
        $subform_state = SubformState::createForSubform($form['plugin_configuration'], $form, $form_state);
        $plugin_instance->submitConfigurationForm($form['plugin_configuration'], $subform_state);

        $plugin_configuration = $plugin_instance->getConfiguration();
        $existing_config[$selected_plugin] = $plugin_configuration;
        $config->set('plugin_configuration', $existing_config);
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error saving plugin configuration: @message', ['@message' => $e->getMessage()]));
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Get storage plugin options for the select list.
   *
   * @return array
   *   An array of plugin options.
   */
  protected function getStoragePluginOptions() {
    $options = [];

    foreach ($this->storagePluginManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $options[$plugin_id] = $plugin_definition['label'];
    }

    return $options;
  }

}
