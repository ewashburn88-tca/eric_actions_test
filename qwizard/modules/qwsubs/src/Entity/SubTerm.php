<?php

namespace Drupal\qwsubs\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\user\UserInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Defines the Subscription Term entity.
 *
 * @ingroup qwsubs
 *
 * @ContentEntityType(
 *   id = "subterm",
 *   label = @Translation("Subscription Term"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwsubs\SubTermListBuilder",
 *     "views_data" = "Drupal\qwsubs\Entity\SubTermViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qwsubs\Form\SubTermForm",
 *       "add" = "Drupal\qwsubs\Form\SubTermForm",
 *       "edit" = "Drupal\qwsubs\Form\SubTermForm",
 *       "delete" = "Drupal\qwsubs\Form\SubTermDeleteForm",
 *     },
 *     "access" = "Drupal\qwsubs\SubTermAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qwsubs\SubTermHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "subterm",
 *   admin_permission = "administer subscription term entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "comment",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/subscriptions/subterm/{subterm}",
 *     "add-form" = "/admin/qwizard/subscriptions/subterm/add",
 *     "edit-form" = "/admin/qwizard/subscriptions/subterm/{subterm}/edit",
 *     "delete-form" = "/admin/qwizard/subscriptions/subterm/{subterm}/delete",
 *     "collection" = "/admin/qwizard/subscriptions/subterm",
 *   },
 *   field_ui_base_route = "subterm.settings"
 * )
 */
class SubTerm extends ContentEntityBase implements SubTermInterface {

  use EntityChangedTrait;

  /**
   * Include the messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

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
   * Retrieves comment.
   *
   * @return mixed
   */
  public function getComment() {
    return $this->get('comment')->value;
  }

  /**
   * Sets the comment.
   *
   * @param $comment
   *
   * @return $this
   */
  public function setComment($comment) {
    $this->set('comment', $comment);
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
   * Determines if this is a current subterms.
   *
   * @return bool
   */
  public function isCurrent() {
    $end = new \DateTime($this->end->getValue());
    $now = new \DateTime('now');
    if ($end >= $now) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the end date of the subterm.
   *
   * @param bool $as_datetime
   *
   * @return \DateTime|mixed
   */
  public function getEnd($as_datetime = FALSE) {
    $end = $this->get('end')->value;
    if ($as_datetime) {
      return new \DateTime($end);
    }
    return $end;
  }

  /**
   * Sets the end date of the subterm.
   *
   * @param \DateTime|string $end
   *
   * @return $this|\Drupal\qwsubs\Entity\SubTerm
   * @throws \Exception
   */
  public function setEnd($end) {
    $end = QwizardGeneral::formatIsoDate($end);
    $this->set('end', $end);
    return $this;
  }

  /**
   * Gets the start date of the subterm.
   *
   * @param bool $as_datetime
   *
   * @return \DateTime|mixed
   */
  public function getStart($as_datetime = FALSE) {
    $start = $this->get('start')->value;
    if ($as_datetime) {
      return new \DateTime($start);
    }
    return $start;
  }

  /**
   * Sets the start date of the subterm.
   *
   * @param \DateTime|string $start
   *
   * @return $this|\Drupal\qwsubs\Entity\SubTerm
   * @throws \Exception
   */
  public function setStart($start) {
    $start = QwizardGeneral::formatIsoDate($start);
    $this->set('start', $start);
    return $this;
  }

  /**
   * Gets the interval to end of subterm, may be negative if expired.
   *
   * @return object DateInterval Object
   */
  public function getRemaining() {
    $end = new \DateTime($this->getEnd());
    $now = new \DateTime('NOW');
    return date_diff($end, $now);
  }

  /**
   * Closes out a subterm.
   *
   * @param string $reason
   * @param null   $end
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cancelSubTerm($reason = '', $end = NULL) {
    if (empty($end)) {
      $this->set('end', date('c', time()));
    }
    else {
      $this->set('end', qwsubs_date_format_iso8601($end));
    }
    $this->setComment($reason);
    $this->save();
  }

  /**
   * Gets the parent subscription id.
   *
   * @return mixed
   */
  public function getSubscriptionId() {
    return $this->get('subscription_id')->target_id;
  }

  /**
   * Is the subterm/membership created by an admin?
   *
   * @return mixed
   */
  public function isAdminCreated() {
    $comment = $this->getComment();
    $is_admin_created = 0;
    if(str_contains(strtolower($comment), 'admin')){
      $is_admin_created = 1;
    }

    return $is_admin_created;
  }

  /**
   * Is the subterm/membership created by an admin?
   *
   * @return mixed
   */
  public function isRenewal() {
    $comment = $this->getComment();
    $is_renewal = 0;
    if($comment == 'Renew membership'){
      $is_renewal = 1;
    }

    return $is_renewal;
  }

  /**
   * Gets the fully loaded parent subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubscription() {
    $sub_storage = \Drupal::entityTypeManager()->getStorage('subscription');
    return  $sub_storage->load($this->getSubscriptionId());
  }

  public function getCourse(){

  }

  /**
   * Retrieves all subscription term ids for a given subscription.
   *
   * @param $subscription_id
   *
   * @return array
   */
  public static function getSubTerms($subscription_id) {
    $con   = \Drupal\Core\Database\Database::getConnection();
    $query = $con->select('subterm', 'st');
    $query->fields('st', 'id');
    $query->condition('st.subscription_id', $subscription_id);
    $subterms = $query->execute()->fetchCol();
    if (empty($subterms)) {
      return [];
    }
    return $subterms;
  }

  /**
   * @param $subscription_id
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getActiveSubTerm($subscription_id) {
    $con   = \Drupal\Core\Database\Database::getConnection();
    $query = $con->select('subterm', 'st');
    $query->fields('st', 'id');
    $query->condition('st.subscription_id', $subscription_id);
    $query->condition('st.end', qwsubs_date_format_iso8601(time()), '>');
    $subterm_id = $query->execute()->fetchField();
    if($subterm_id) {
      $subterm_storage = \Drupal::entityTypeManager()->getStorage('subterm');
      $subterm = $subterm_storage->load($subterm_id);
      return $subterm;
    }
    return FALSE;
  }

  /**
   * Gets a subterm by its UUID.
   *
   * @param $uuid
   *
   * @return \Drupal\qwsubs\Entity\SubTerm | NULL
   */
  public static function getSubTermByUuid($uuid) {
    $storage     = \Drupal::entityTypeManager()->getStorage('subterm');
    $query       = \Drupal::entityQuery('subterm')
      ->condition('uuid', $uuid);
    $subterm_ids = $query->execute();
    $subterm_id = reset($subterm_ids);
    return $storage->load($subterm_id);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID of author of the Subscription Term entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['comment'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Comment'))
      ->setDescription(t('The comment of the Subscription Term entity.'))
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

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['start'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Start Date & Time'))
      ->setDescription(t('The start datetime (ISO) of the Subscription entity.'))
      ->setSettings([
        'max_length' => 30,
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

    $fields['end'] = BaseFieldDefinition::create('string')
      ->setLabel(t('End Date & Time'))
      ->setDescription(t('The end datetime (ISO) of the Subscription entity.'))
      ->setSettings([
        'max_length' => 30,
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
      ->setDisplayConfigurable('view', TRUE);

    $fields['subscription_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subscription id'))
      ->setDescription(t('The ID of subscription entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'subscription')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => 0,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ),
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    /*$fields['admin_created'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Admin created'))
      ->setDescription(t('A boolean indicating whether the subterm was created via an admin.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type'   => 'boolean_checkbox',
        'weight' => -3,
      ]);*/

    return $fields;
  }

}
