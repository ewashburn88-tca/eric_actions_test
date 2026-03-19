<?php

namespace Drupal\qwarchive\Plugin;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for QW archive storage plugins.
 */
interface QwArchiveStoragePluginInterface extends PluginInspectionInterface, ConfigurableInterface, PluginFormInterface {

  /**
   * Store a JSON file.
   *
   * @param string $filename
   *   The filename to store.
   * @param string $json_data
   *   The JSON data to store.
   * @param string|null $sub_dir
   *   The sub-directory.
   *
   * @return bool|string
   *   The file URI on success, FALSE on failure.
   */
  public function storeJsonFile($filename, $json_data, $sub_dir = NULL);

  /**
   * Reads a JSON file.
   *
   * @param string $filename
   *   The filename to read.
   * @param string|null $sub_dir
   *   The sub-directory.
   *
   * @return bool|string
   *   The JSON data, FALSE on failure.
   */
  public function readJsonFile($filename, $sub_dir = NULL);

  /**
   * Deletes a JSON file.
   *
   * @param string $filename
   *   The filename to read.
   * @param string|null $sub_dir
   *   The sub-directory.
   */
  public function deleteJsonFile($filename, $sub_dir = NULL);

  /**
   * Deletes a directory of JSON files for user.
   *
   * @param string $sub_dir
   *   The sub-directory.
   */
  public function deleteJsonDirectory($sub_dir);

  /**
   * Get the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel();

  /**
   * Get the plugin description.
   *
   * @return string
   *   The plugin description.
   */
  public function getDescription();

}
