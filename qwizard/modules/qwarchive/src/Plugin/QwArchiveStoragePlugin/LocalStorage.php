<?php

namespace Drupal\qwarchive\Plugin\QwArchiveStoragePlugin;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\qwarchive\Plugin\QwArchiveStoragePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a local file storage plugin.
 *
 * @QwArchiveStoragePlugin(
 *   id = "local_storage",
 *   label = @Translation("Local"),
 *   description = @Translation("Store files in the local file system.")
 * )
 */
class LocalStorage extends QwArchiveStoragePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The file system.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The stream wrapper.
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * Constructs a LocalStorage object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileSystemInterface $file_system, StreamWrapperManagerInterface $stream_wrapper_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileSystem = $file_system;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'file_scheme' => 'private',
      'directory' => 'qwarchive',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['file_scheme'] = [
      '#type' => 'select',
      '#title' => $this->t('File Scheme'),
      '#description' => $this->t('Select the file scheme for storing the files.'),
      '#options' => $this->streamWrapperManager->getNames(),
      '#default_value' => $this->configuration['file_scheme'],
      '#required' => TRUE,
    ];

    $form['directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Directory'),
      '#description' => $this->t('The directory for storing the files.'),
      '#default_value' => $this->configuration['directory'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['file_scheme'] = $form_state->getValue('file_scheme');
    $this->configuration['directory'] = $form_state->getValue('directory');
  }

  /**
   * {@inheritdoc}
   */
  public function storeJsonFile($filename, $json_data, $sub_dir = NULL) {
    $scheme = $this->configuration['file_scheme'];
    $directory = $scheme . '://' . $this->configuration['directory'];

    if ($sub_dir) {
      $directory .= '/' . $sub_dir;
    }

    // Prepare the directory.
    if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      throw new \Exception('Failed to prepare directory ' . $directory);
    }

    if (!str_ends_with($filename, '.json')) {
      $filename .= '.json';
    }

    $destination = $directory . '/' . $filename;

    try {
      $filepath = $this->fileSystem->realpath($destination);
      $result = file_put_contents($filepath, $json_data);
      if ($result == FALSE) {
        throw new \Exception('Failed to write json into file ' . $filepath);
      }
    }
    catch (\Exception $e) {
      throw new \Exception('Exception while saving JSON file: ' . $e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function readJsonFile($filename, $sub_dir = NULL) {
    $scheme = $this->configuration['file_scheme'];
    $directory = $scheme . '://' . $this->configuration['directory'];

    if ($sub_dir) {
      $directory .= '/' . $sub_dir;
    }

    if (!str_ends_with($filename, '.json')) {
      $filename .= '.json';
    }

    $json_file = $directory . '/' . $filename;

    $json_data = FALSE;

    try {
      $filepath = $this->fileSystem->realpath($json_file);
      $json_data = file_get_contents($filepath);
      if ($json_data == FALSE) {
        throw new \Exception('Failed to read json file ' . $filepath);
      }
    }
    catch (\Exception $e) {
      throw new \Exception('Exception while reading JSON file: ' . $e->getMessage());
    }

    return $json_data;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteJsonFile($filename, $sub_dir = NULL) {
    $scheme = $this->configuration['file_scheme'];
    $directory = $scheme . '://' . $this->configuration['directory'];

    if ($sub_dir) {
      $directory .= '/' . $sub_dir;
    }

    if (!str_ends_with($filename, '.json')) {
      $filename .= '.json';
    }

    $json_file = $directory . '/' . $filename;

    $this->fileSystem->delete($json_file);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteJsonDirectory($sub_dir) {
    $scheme = $this->configuration['file_scheme'];
    $directory = $scheme . '://' . $this->configuration['directory'];

    if ($sub_dir) {
      $directory .= '/' . $sub_dir;
    }

    $this->fileSystem->deleteRecursive($directory);
  }

}
