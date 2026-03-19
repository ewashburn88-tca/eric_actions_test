<?php

namespace Drupal\qwarchive\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a QW archive storage plugin annotation.
 *
 * @Annotation
 */
class QwArchiveStoragePlugin extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the storage plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the storage plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

}
