<?php

namespace Drupal\qwarchive;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\qwarchive\Plugin\QwArchiveStoragePluginInterface;
use Drupal\qwarchive\Plugin\QwArchiveStoragePluginManager;
use Psr\Log\LoggerInterface;

/**
 * The qwarchive storage manager.
 */
class QwArchiveStorageManager {

  /**
   * The storage plugin manager.
   */
  protected QwArchiveStoragePluginManager $storagePluginManager;

  /**
   * The config factory.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * Constructs a new QwArchiveStorageManager instance.
   *
   * @param \Drupal\qwarchive\Plugin\QwArchiveStoragePluginManager $storage_plugin_manager
   *   The storage plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(QwArchiveStoragePluginManager $storage_plugin_manager, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->storagePluginManager = $storage_plugin_manager;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('qwarchive');
  }

  /**
   * Get the configured storage plugin instance.
   *
   * @return \Drupal\qwarchive\Plugin\QwArchiveStoragePluginInterface|null
   *   The storage plugin instance or NULL if not configured.
   */
  public function getStoragePlugin() {
    $config = $this->configFactory->get('qwarchive.settings');
    $plugin_id = $config->get('storage_plugin');

    if (empty($plugin_id)) {
      $this->logger->warning('No storage plugin configured.');
      return NULL;
    }

    $plugin_configuration = $config->get('plugin_configuration');
    $configuration = $plugin_configuration[$plugin_id] ?? [];
    return $this->storagePluginManager->createInstance($plugin_id, $configuration);
  }

  /**
   * Store a JSON file using the configured plugin.
   *
   * @param string $filename
   *   The filename.
   * @param mixed $data
   *   The data to store (will be JSON encoded if not already a string).
   * @param string|null $sub_dir
   *   The sub-directory.
   */
  public function storeJsonFile($filename, $data, $sub_dir = NULL) {
    $plugin = $this->getStoragePlugin();

    if (!$plugin instanceof QwArchiveStoragePluginInterface) {
      $this->logger->error('Storage plugin not available.');
      throw new \Exception('Storage plugin not available.');
    }

    // Convert data to JSON if needed.
    if (!is_string($data)) {
      $json_data = Json::encode($data);
      if ($json_data === FALSE) {
        $this->logger->error('Error while encoding data as JSON.');
        throw new \Exception('Error while encoding data into JSON.');
      }
    }
    else {
      $json_data = $data;
    }

    $plugin->storeJsonFile($filename, $json_data, $sub_dir);
  }

  /**
   * Reads data from JSON using the configured plugin.
   *
   * @param string $filename
   *   The filename.
   * @param string $uid
   *   The user id, mainly used as sub-directory.
   *
   * @return bool|array
   *   The parsed JSON data, FALSE on failure.
   */
  public function readJsonFile($filename, $uid) {
    $plugin = $this->getStoragePlugin();

    if (!$plugin instanceof QwArchiveStoragePluginInterface) {
      $this->logger->error('Storage plugin not available.');
      throw new \Exception('Storage plugin not available.');
    }

    $json_data = $plugin->readJsonFile($filename, $uid);

    // Decode the json data.
    if (!is_string($json_data)) {
      $this->logger->error('Error while reading data as JSON.');
      throw new \Exception('Error while reading data from JSON.');
    }

    $data = FALSE;
    try {
      // Parse the data.
      $data = Json::decode($json_data);
    }
    catch (\Exception $e) {
      throw new \Exception('Exception while parsing the JSON data: ' . $e->getMessage());
    }

    return $data;
  }

  /**
   * Deletes the given json file.
   *
   * @param string $filename
   *   The filename.
   * @param string $uid
   *   The user id, mainly used as sub-directory.
   */
  public function deleteJsonFile($filename, $uid) {
    $plugin = $this->getStoragePlugin();

    if (!$plugin instanceof QwArchiveStoragePluginInterface) {
      $this->logger->error('Storage plugin not available.');
      throw new \Exception('Storage plugin not available.');
    }
    try {
      $plugin->deleteJsonFile($filename, $uid);
    }
    catch (\Exception $e) {
      throw new \Exception('Exception while deleting the json file: ' . $e->getMessage());
    }

  }

  /**
   * Deletes the directory of json files for user.
   *
   * @param string $uid
   *   The user id, mainly used as sub-directory.
   */
  public function deleteJsonDirectory($uid) {
    $plugin = $this->getStoragePlugin();

    if (!$plugin instanceof QwArchiveStoragePluginInterface) {
      $this->logger->error('Storage plugin not available.');
      throw new \Exception('Storage plugin not available.');
    }

    try {
      $plugin->deleteJsonDirectory($uid);
    }
    catch (\Exception $e) {
      throw new \Exception('Exception while deleting the directory: ' . $e->getMessage());
    }
  }

}
