<?php

namespace Drupal\qwschools\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the School entity.
 *
 * @ingroup qwschools
 *
 * @ContentEntityType(
 *   id = "school",
 *   label = @Translation("School"),
 *   bundle_label = @Translation("School type"),
 *   handlers = {
 *     "storage" = "Drupal\qwschools\SchoolStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwschools\SchoolListBuilder",
 *     "views_data" = "Drupal\qwschools\Entity\SchoolViewsData",
 *     "translation" = "Drupal\qwschools\SchoolTranslationHandler",
 *
 *     "form" = {
 *       "default" = "Drupal\qwschools\Form\SchoolForm",
 *       "add" = "Drupal\qwschools\Form\SchoolForm",
 *       "edit" = "Drupal\qwschools\Form\SchoolForm",
 *       "delete" = "Drupal\qwschools\Form\SchoolDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\qwschools\SchoolHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\qwschools\SchoolAccessControlHandler",
 *   },
 *   base_table = "school",
 *   data_table = "school_field_data",
 *   revision_table = "school_revision",
 *   revision_data_table = "school_field_revision",
 *   translatable = TRUE,
 *   permission_granularity = "bundle",
 *   admin_permission = "administer school entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/school/{school}",
 *     "add-page" = "/admin/qwizard/school/add",
 *     "add-form" = "/admin/qwizard/school/add/{school_type}",
 *     "edit-form" = "/admin/qwizard/school/{school}/edit",
 *     "delete-form" = "/admin/qwizard/school/{school}/delete",
 *     "version-history" = "/admin/qwizard/school/{school}/revisions",
 *     "revision" = "/admin/qwizard/school/{school}/revisions/{school_revision}/view",
 *     "revision_revert" = "/admin/qwizard/school/{school}/revisions/{school_revision}/revert",
 *     "revision_delete" = "/admin/qwizard/school/{school}/revisions/{school_revision}/delete",
 *     "translation_revert" = "/admin/qwizard/school/{school}/revisions/{school_revision}/revert/{langcode}",
 *     "collection" = "/admin/qwizard/school",
 *   },
 *   bundle_entity_type = "school_type",
 *   field_ui_base_route = "entity.school_type.edit_form"
 * )
 */
class School extends EditorialContentEntityBase implements SchoolInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the School entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status']->setDescription(t('A boolean indicating whether the School is published.'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
