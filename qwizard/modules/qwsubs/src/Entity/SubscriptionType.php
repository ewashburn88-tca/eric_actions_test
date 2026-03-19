<?php

namespace Drupal\qwsubs\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Subscription type entity.
 *
 * @ConfigEntityType(
 *   id = "subscription_type",
 *   label = @Translation("Subscription type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwsubs\SubscriptionTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\qwsubs\Form\SubscriptionTypeForm",
 *       "edit" = "Drupal\qwsubs\Form\SubscriptionTypeForm",
 *       "delete" = "Drupal\qwsubs\Form\SubscriptionTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwsubs\SubscriptionTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "subscription_type",
 *   admin_permission = "administer site configuration",
 *   bundle_of = "subscription",
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
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/subscriptions/subscription_type/{subscription_type}",
 *     "add-form" = "/admin/qwizard/subscriptions/subscription_type/add",
 *     "edit-form" = "/admin/qwizard/subscriptions/subscription_type/{subscription_type}/edit",
 *     "delete-form" = "/admin/qwizard/subscriptions/subscription_type/{subscription_type}/delete",
 *     "collection" = "/admin/qwizard/subscriptions/subscription_type"
 *   }
 * )
 */
class SubscriptionType extends ConfigEntityBundleBase implements SubscriptionTypeInterface {

  /**
   * The Subscription type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Subscription type label.
   *
   * @var string
   */
  protected $label;

}
