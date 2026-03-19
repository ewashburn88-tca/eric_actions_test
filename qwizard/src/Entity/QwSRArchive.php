<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Student Record Archive entity.
 *
 * @ingroup qwizard
 *
 * @ContentEntityType(
 *   id = "qwsr_archive",
 *   label = @Translation("Student Record Archive"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwSRArchiveListBuilder",
 *     "views_data" = "Drupal\qwizard\Entity\QwSRArchiveViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qwizard\Form\QwSRArchiveForm",
 *       "add" = "Drupal\qwizard\Form\QwSRArchiveForm",
 *       "edit" = "Drupal\qwizard\Form\QwSRArchiveForm",
 *       "delete" = "Drupal\qwizard\Form\QwSRArchiveDeleteForm",
 *     },
 *     "access" = "Drupal\qwizard\QwSRArchiveAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwSRArchiveHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "qwsr_archive",
 *   admin_permission = "administer student record archive entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/qwsr_archive/{qwsr_archive}",
 *     "add-form" = "/admin/qwizard/qwsr_archive/add",
 *     "edit-form" = "/admin/qwizard/qwsr_archive/{qwsr_archive}/edit",
 *     "delete-form" = "/admin/qwizard/qwsr_archive/{qwsr_archive}/delete",
 *     "collection" = "/admin/qwizard/qwsr_archive",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   field_ui_base_route = "qwsr_archive.settings"
 * )
 */
class QwSRArchive extends ContentEntityBase implements QwSRArchiveInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
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
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isCurrent() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrent($current) {
    $this->set('status', $current ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Student Record Archive entity.'))
      ->setSettings([
        'max_length' => 50,
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

    $fields['user_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user ID of the Student Record Archive.'))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 4,
      ))
      ->setDisplayOptions('form', array(
        'weight' => 4,
      ))
      ->setDisplayConfigurable('form', true)
      ->setDisplayConfigurable('view', true);

    $fields['subscription_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subscription ID'))
      ->setDescription(t('The students subscription id of the Student Record Archive.'))
      ->setSettings([
        'max_length' => 50,
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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Student Record Archive is current.'))
      ->setDefaultValue(TRUE)
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

    $fields['snapshot'] = BaseFieldDefinition::create('jsonb')
      ->setLabel(t('Student Results Snapshot'))
      ->setDescription(t('JSON Array of all student results as last updated.'))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => 4,
      ))
      ->setDisplayConfigurable('view', true);

    return $fields;
  }

}
