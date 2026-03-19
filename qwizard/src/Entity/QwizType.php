<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Quiz type entity.
 *
 * @ConfigEntityType(
 *   id = "qwiz_type",
 *   label = @Translation("Quiz type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwizTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\qwizard\Form\QwizTypeForm",
 *       "edit" = "Drupal\qwizard\Form\QwizTypeForm",
 *       "delete" = "Drupal\qwizard\Form\QwizTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwizTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "qwiz_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "qwiz",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/qwiz_type/{qwiz_type}",
 *     "add-form" = "/admin/qwizard/qwiz_type/add",
 *     "edit-form" = "/admin/qwizard/qwiz_type/{qwiz_type}/edit",
 *     "delete-form" = "/admin/qwizard/qwiz_type/{qwiz_type}/delete",
 *     "collection" = "/admin/qwizard/qwiz_type"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 * )
 */
class QwizType extends ConfigEntityBundleBase implements QwizTypeInterface {

  /**
   * The Quiz type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Quiz type label.
   *
   * @var string
   */
  protected $label;

}
