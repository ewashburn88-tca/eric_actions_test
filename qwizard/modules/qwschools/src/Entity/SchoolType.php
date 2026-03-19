<?php

namespace Drupal\qwschools\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the School type entity.
 *
 * @ConfigEntityType(
 *   id = "school_type",
 *   label = @Translation("School type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwschools\SchoolTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\qwschools\Form\SchoolTypeForm",
 *       "edit" = "Drupal\qwschools\Form\SchoolTypeForm",
 *       "delete" = "Drupal\qwschools\Form\SchoolTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwschools\SchoolTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "school_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "school",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/school_type/{school_type}",
 *     "add-form" = "/admin/qwizard/school_type/add",
 *     "edit-form" = "/admin/qwizard/school_type/{school_type}/edit",
 *     "delete-form" = "/admin/qwizard/school_type/{school_type}/delete",
 *     "collection" = "/admin/qwizard/school_type"
 *   }
 * )
 */
class SchoolType extends ConfigEntityBundleBase implements SchoolTypeInterface {

  /**
   * The School type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The School type label.
   *
   * @var string
   */
  protected $label;

}
