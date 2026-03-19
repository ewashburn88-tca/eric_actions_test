<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Qw student results type entity.
 *
 * @ConfigEntityType(
 *   id = "qw_student_results_type",
 *   label = @Translation("Qw student results type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwStudentResultsTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\qwizard\Form\QwStudentResultsTypeForm",
 *       "edit" = "Drupal\qwizard\Form\QwStudentResultsTypeForm",
 *       "delete" = "Drupal\qwizard\Form\QwStudentResultsTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwStudentResultsTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "qw_student_results_type",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/structure/qw_student_results_type/{qw_student_results_type}",
 *     "add-form" = "/admin/qwizard/structure/qw_student_results_type/add",
 *     "edit-form" = "/admin/qwizard/structure/qw_student_results_type/{qw_student_results_type}/edit",
 *     "delete-form" = "/admin/qwizard/structure/qw_student_results_type/{qw_student_results_type}/delete",
 *     "collection" = "/admin/qwizard/structure/qw_student_results_type"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 * )
 */
class QwStudentResultsType extends ConfigEntityBase implements QwStudentResultsTypeInterface {

  /**
   * The Qw student results type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Qw student results type label.
   *
   * @var string
   */
  protected $label;

}
