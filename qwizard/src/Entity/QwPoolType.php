<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Question Pool type entity.
 *
 * @ConfigEntityType(
 *   id = "qwpool_type",
 *   label = @Translation("Question Pool type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwPoolTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\qwizard\Form\QwPoolTypeForm",
 *       "edit" = "Drupal\qwizard\Form\QwPoolTypeForm",
 *       "delete" = "Drupal\qwizard\Form\QwPoolTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwPoolTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "qwpool_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "qwpool",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "defaultDecrement",
 *     "defaultDecrWrong",
 *     "defaultDecrSkipped",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/qwpool_type/{qwpool_type}",
 *     "add-form" = "/admin/qwizard/qwpool_type/add",
 *     "edit-form" = "/admin/qwizard/qwpool_type/{qwpool_type}/edit",
 *     "delete-form" = "/admin/qwizard/qwpool_type/{qwpool_type}/delete",
 *     "collection" = "/admin/qwizard/qwpool_type"
 *   }
 * )
 */
class QwPoolType extends ConfigEntityBundleBase implements QwPoolTypeInterface {

  /**
   * The Question Pool type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Question Pool type label.
   *
   * @var string
   */
  protected $label;

  /**
   * Sets the default decrement value of the pool.
   *
   * @var bool
   */
  protected $defaultDecrement;

  /**
   * Sets the default decrement value of the pool.
   *
   * @var bool
   */
  protected $defaultDecrWrong;

  /**
   * Sets the default decrement value of the pool.
   *
   * @var bool
   */
  protected $defaultDecrSkipped;

  /**
   * {@inheritdoc}
   */
  public function isDefaultDecrement(): bool {
    return isset($this->defaultDecrement) ? $this->defaultDecrement : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultDecrement(bool $defaultDecrement): void {
    $this->defaultDecrement = $defaultDecrement;
  }
  /**
   * {@inheritdoc}
   */
  public function isDefaultDecrWrong(): bool {
    return isset($this->defaultDecrWrong) ? $this->defaultDecrWrong : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultDecrWrong(bool $defaultDecrWrong): void {
    $this->defaultDecrWrong = $defaultDecrWrong;
  }
  /**
   * {@inheritdoc}
   */
  public function isDefaultDecrSkipped(): bool {
    return isset($this->defaultDecrSkipped) ? $this->defaultDecrSkipped : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultDecrSkipped(bool $defaultDecrSkipped): void {
    $this->defaultDecrSkipped = $defaultDecrSkipped;
  }
}
