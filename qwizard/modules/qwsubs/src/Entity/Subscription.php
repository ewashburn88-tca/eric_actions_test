<?php

namespace Drupal\qwsubs\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Drupal\user\RoleInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Defines the Subscription entity.
 *
 * @ingroup qwsubs
 *
 * @ContentEntityType(
 *   id = "subscription",
 *   label = @Translation("Subscription"),
 *   bundle_label = @Translation("Subscription type"),
 *   handlers = {
 *     "storage" = "Drupal\qwsubs\SubscriptionStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwsubs\SubscriptionListBuilder",
 *     "views_data" = "Drupal\qwsubs\Entity\SubscriptionViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qwsubs\Form\SubscriptionForm",
 *       "add" = "Drupal\qwsubs\Form\SubscriptionForm",
 *       "edit" = "Drupal\qwsubs\Form\SubscriptionForm",
 *       "delete" = "Drupal\qwsubs\Form\SubscriptionDeleteForm",
 *     },
 *     "access" = "Drupal\qwsubs\SubscriptionAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qwsubs\SubscriptionHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "subscription",
 *   revision_table = "subscription_revision",
 *   revision_data_table = "subscription_field_revision",
 *   admin_permission = "administer subscription entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   links = {
 *     "canonical" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}",
 *     "add-page" = "/admin/qwizard/subscriptions/subscription/add",
 *     "add-form" =
 *     "/admin/qwizard/subscriptions/subscription/add/{subscription_type}",
 *     "edit-form" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}/edit",
 *     "delete-form" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}/delete",
 *     "version-history" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}/revisions",
 *     "revision" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}/revisions/{subscription_revision}/view",
 *     "revision_revert" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}/revisions/{subscription_revision}/revert",
 *     "revision_delete" =
 *     "/admin/qwizard/subscriptions/subscription/{subscription}/revisions/{subscription_revision}/delete",
 *     "collection" = "/admin/qwizard/subscriptions/subscription",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 *   bundle_entity_type = "subscription_type",
 *   field_ui_base_route = "entity.subscription_type.edit_form"
 * )
 */
class Subscription extends RevisionableContentEntityBase implements SubscriptionInterface {

  use EntityChangedTrait;
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    if (empty($values['user_id'])) {
      $values += [
        'user_id' => \Drupal::currentUser()->id(),
      ];
    }
  }

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

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly, make the subscription owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }
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
  public function getCreated() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreated($timestamp) {
    $this->set('created', $timestamp);
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
  public function getCourseId() {
    return $this->get('course')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setCourseId($tid) {
    $this->set('course', $tid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExtensionLimit() {
    $value = $this->get('extension_limit')->value;
    if($value === null){
      $value = \Drupal::service('qwizard.general')->getStatics('extension_default_limit');
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function setExtensionLimit($limit_count) {
    $this->set('extension_limit', $limit_count);
    return $this;
  }

  /**
   * Get the amount of renewals remaining for a subscription
   * @return int
   */
  public function getRemainingRenewals(){
    $QWGeneral = \Drupal::service('qwizard.general');
    $max_limit = $QWGeneral->getMaxSubscriptionAmount($this);
    $subs = $this->getSubTerms(false);

    foreach($subs as $sub){
      if($sub->isRenewal()){
        $max_limit = $max_limit - 1;
      }
    }

    return max(0, $max_limit);
  }

  /**
   * {@inheritdoc}
   */
  public function getPremium() {
    return $this->get('premium')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPremium($premium) {
    $this->set('premium', $premium);
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
  public function isActive() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setActive($active = TRUE) {
    $this->set('status', $active ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles() {
    $roles = [];

    foreach ($this->get('roles') as $role) {
      if ($role->target_id) {
        $roles[] = $role->target_id;
      }
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function hasRole($rid) {
    return in_array($rid, $this->getRoles());
  }

  /**
   * {@inheritdoc}
   */
  public function addRole($rid) {

    if (in_array($rid, [
      RoleInterface::AUTHENTICATED_ID,
      RoleInterface::ANONYMOUS_ID,
    ])) {
      throw new \InvalidArgumentException('Anonymous or authenticated role ID cannot expire.');
    }

    $roles   = $this->getRoles();
    $roles[] = $rid;
    $this->set('roles', array_unique($roles));
  }

  /**
   * {@inheritdoc}
   */
  public function removeRole($rid) {
    $this->set('roles', array_diff($this->getRoles(), [$rid]));
  }

  /**
   * Gets the data field as native json or as php array.
   *
   * @param bool $as_json
   *
   * @return array|Json
   */
  public function getDataArray($as_json = FALSE) {
    $data = $this->data->value;
    if ($as_json) {
      return $data;
    }
    $data_array = Json::decode($data);
    return $data_array;
  }

  /**
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getActiveSubTerm() {
    $subterm = SubTerm::getActiveSubTerm($this->get('id'));
    return $subterm;
  }

  /**
   * Activates the subscription if there is a current subterm.
   *
   * @return $this|bool|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function activateSubscription() {
    // Try to get active subterm.
    $subterm = $this->getActiveSubTerm();
    if (empty($subterm)) {
      // Can't activate if no current subterm.
      return FALSE;
    }
    $this->setActive();
    $acct  = $this->getOwner();
    $roles = $this->getRoles();
    foreach ($roles as $role) {
      $acct->addRole($role);
    }
    $acct->save();
    return $this;
  }

  /**
   * Activates the subscription, creating term.
   *
   * @param $end
   *
   * @return $this|mixed
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   * @see        createSubscription()
   * @see        activateSubscription()
   *
   * @deprecated Subterm should be created with subscription, then activated.
   */
  public function activateSubscriptionCreateTerm($end) {
    // First handle max term if there are max_term.
    $max_term = $this->get('max_term');
    // Check if term
    if ($max_term == 0) {
      // @todo: Make this message configurable.
      $message = $this->t('You have exceeded the maximum subscription length.');
      $this->messenger->addWarning($message);
      return $this;
    }
    $now      = new \DateTime('now');
    $end      = qwsubs_date_format_iso8601($end);
    $end_date = new \DateTime($end);

    // Set max term.
    if ($max_term > 0) {
      $term         = $end_date->diff($now);
      $new_max_term = $max_term - $term;
      $max_term     = $new_max_term > 0 ? $new_max_term : 0;
      $this->max_term->setValue($max_term);
    }

    // Create Subscription term.
    $properties = [
      'comment'         => $this->getName() . '-' . $end,
      'start'           => $now,
      'end'             => $end,
      'subscription_id' => $this->get('id'),
      'user_id'         => $this->getOwnerId(),
    ];
    $subterm    = SubTerm::create($properties);
    try {
      $subterm->save();
    }
    catch (EntityStorageException $e) {
      $this->messenger->addMessage($e->getMessage());
      return $this;
    }
    $this->set('status', TRUE);
    $this->save();
    return $this;
  }

  /**
   * Deactivates the subscription and removes the roles from the user.
   *
   * @return bool
   */
  public function deActivateSubscription() {
    // Remove roles, record history.
    $sub_roles = $this->getRoles();
    $acct      = $this->getOwner();
    foreach ($sub_roles as $role) {
      $acct->removeRole($role);
    }
    $this->setActive(FALSE);
    try {
      // @todo: Handle users deleted without sub deleted.
      $acct->save();
      $this->save();
      return TRUE;
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('qwsubs')->error($e->getMessage());
      echo $e->getMessage();
      return FALSE;
    }
  }

  /**
   * Cancel the subscription and remove roles.
   *
   * @param string $reason
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cancelSubscription($reason = '') {
    // @todo: Find and end current SubTerm.
    $subterm = SubTerm::getActiveSubTerm($this->get('id'));
    $subterm->cancelSubTerm($reason);
    $this->deActivateSubscription();
    $this->save();
  }

  /**
   * Gets the current term of the subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCurrentSubTerm() {
    $now         = date('c', time());
    $storage     = \Drupal::entityTypeManager()->getStorage('subterm');
    $query       = \Drupal::entityQuery('subterm')
      ->condition('subscription_id', $this->id())
      ->condition('end', $now, '>');
    $subterm_ids = $query->execute();

    if (empty($subterm_ids)) {
      return FALSE;
    }

    if (count($subterm_ids) > 1) {
      // This shouldn't be
      \Drupal::logger('qwsubs')->notice('Multiple active subterms exist for subscription '.$this->id());
    }
    $subterm_id = reset($subterm_ids);

    return $storage->load($subterm_id);
  }

  /**
   * Gets the current datetime of current subterm end.
   *
   * @param bool $as_datetime
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCurrentTermEnd($as_datetime = FALSE) {
    $subterm = $this->getCurrentSubTerm();
    if (!empty($subterm)) {
      return $subterm->getEnd($as_datetime);
    }
    return FALSE;
  }

  /**
   * Get the most recent SubTerm, current or not.
   *
   * @return \Drupal\qwsubs\Entity\SubTerm
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getLastSubTerm($future_only = true) {
    $subterm_oldest = null;
    $subterms = $this->getSubTerms($future_only);
    $oldest   = new \DateTime();
    $oldest->setTimestamp(0);
    foreach ($subterms as $subterm) {
      // Check if this term is more recent than last.
      $end = new \DateTime($subterm->getEnd());
      if ($end > $oldest) {
        $subterm_oldest = $subterm;
        $oldest         = $end;
      }
    }
    return $subterm_oldest;
  }

  /**
   * Gets the subterms for this subscription.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]|SubTerm[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getSubTerms($future_only = true) {
    $now         = date('Y-m-d', time());
    $storage     = \Drupal::entityTypeManager()->getStorage('subterm');
    $query       = \Drupal::entityQuery('subterm')
      ->condition('subscription_id', $this->id());

    if($future_only) {
      $query->condition('end', $now, '>');
    }
    $subterm_ids = $query->execute();
    $subterms    = $storage->loadMultiple($subterm_ids);

    return $subterms;
  }

  /**
   * Renews the subscription with a subterm time period (interval).
   *
   * This adds a new SubTerm to the current subscription.
   *
   * @param string|\DateInterval $interval If string must be compatible with
   *                                       DateInterval
   *
   * @return $this
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function renewSubscriptionByTerm($interval) {
    if ($this->isActive()) {
      // Get current term.
      $current_subterm = $this->getCurrentSubTerm();
    }
    else {
      // Get last subterm.
      $current_subterm = $this->getLastSubTerm();
    }
    $current_end = $current_subterm->getEnd();
    // Add a new subterm to the subscription.
    // Create term.
    $new_subterm = $current_subterm->createDuplicate();
    // Start new subterm when this one ends.
    $new_subterm->setStart($current_end);
    $current_end_date = QwizardGeneral::getDateTime($current_end);
    // Add the interval.
    if (!($interval instanceof \DateInterval)) {
      $interval = $current_end_date->add($interval);
    }
    $new_end = new DateInterval($interval);

    $new_subterm->setEnd($new_end);
    $new_subterm->save();
    return $this;
  }

  /**
   * Renews the subscription with a subterm start & end.
   *
   * @param      $end
   * @param null $start
   *
   * @return $this
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function renewSubscriptionByDate($end, $start = NULL) {
    if ($start == NULL) {
      $start_date = date('c', time());
    }
    else {
      $start_date = QwizardGeneral::formatIsoDate($start);
    }

    $end_date = QwizardGeneral::formatIsoDate($end);
    if ($this->isActive()) {
      $current_subterm = $this->getCurrentSubTerm();

      // Add a new subterm to the subscription.
      // Create term.
      $new_subterm = $current_subterm->createDuplicate();
      $new_subterm->setStart($start_date);
      $new_subterm->setEnd($end_date);
      $new_subterm->save();
      // @todo: not complete, need to cancel current and replace with new if overlap

      // @todo: if start after end, create a new subterm that isn't active yet
    }
    else {
      // @todo: Just create new subterm with start and end date.
    }
    return $this;
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
  $form['my_entity_settings']['#markup'] = 'Settings form for My Entity entities. Manage field settings here.';
  return $form;
}

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user ID for the Subscription entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label'  => 'hidden',
        'type'   => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type'     => 'entity_reference_autocomplete',
        'weight'   => 5,
        'settings' => [
          'match_operator'    => 'CONTAINS',
          'size'              => '60',
          'autocomplete_type' => 'tags',
          'placeholder'       => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Subscription entity.'))
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length'      => 255,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'type'   => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type'   => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Subscription is active.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type'   => 'boolean_checkbox',
        'weight' => -3,
      ]);


    $fields['premium'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Premium status'))
      ->setDescription(t('A boolean indicating whether the Subscription is premium.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type'   => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['max_term'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('max_term Remaining'))
      ->setDescription(t('Max number of days allowed on subscription.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['extension_limit'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Extension Limit'))
      ->setDescription(t('Max number of purchased extensions allowed on subscription.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setDescription(t('The course of the Question Pool entity.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'courses' => 'courses',
          ],
        ])
      ->setDisplayOptions('view', [
        'label'  => 'hidden',
        'type'   => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type'     => 'entity_reference_autocomplete',
        'weight'   => 3,
        'settings' => [
          'match_operator'    => 'CONTAINS',
          'size'              => '10',
          'autocomplete_type' => 'tags',
          'placeholder'       => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['roles'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Roles'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDescription(t('The roles the user has.'))
      ->setSetting('target_type', 'user_role')
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'type'   => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type'   => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('jsonb')
      ->setLabel(t('Subscription Data'))
      ->setDescription(t('JSON data for subscription.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ]);

    return $fields;
  }

}
