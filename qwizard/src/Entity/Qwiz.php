<?php

namespace Drupal\qwizard\Entity;

use Dompdf\Exception;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\user\UserInterface;
use Drupal\Component\Serialization\Json;
use Drupal\qwizard\QwizardGeneral;

/**
 * Defines the Quiz entity.
 *
 * @ingroup qwizard
 *
 * @ContentEntityType(
 *   id = "qwiz",
 *   label = @Translation("Quiz"),
 *   bundle_label = @Translation("Quiz type"),
 *   handlers = {
 *     "storage" = "Drupal\qwizard\QwizStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwizListBuilder",
 *     "views_data" = "Drupal\qwizard\Entity\QwizViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qwizard\Form\QwizForm",
 *       "add" = "Drupal\qwizard\Form\QwizForm",
 *       "edit" = "Drupal\qwizard\Form\QwizForm",
 *       "delete" = "Drupal\qwizard\Form\QwizDeleteForm",
 *     },
 *     "access" = "Drupal\qwizard\QwizAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwizHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "qwiz",
 *   revision_table = "qwiz_revision",
 *   revision_data_table = "qwiz_field_revision",
 *   admin_permission = "administer quiz entities",
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
 *   links = {
 *     "canonical" = "/admin/qwizard/qwiz/{qwiz}",
 *     "add-page" = "/admin/qwizard/qwiz/add",
 *     "add-form" = "/admin/qwizard/qwiz/add/{qwiz_type}",
 *     "edit-form" = "/admin/qwizard/qwiz/{qwiz}/edit",
 *     "delete-form" = "/admin/qwizard/qwiz/{qwiz}/delete",
 *     "version-history" = "/admin/qwizard/qwiz/{qwiz}/revisions",
 *     "revision" =
 *     "/admin/qwizard/qwiz/{qwiz}/revisions/{qwiz_revision}/view",
 *     "revision_revert" =
 *     "/admin/qwizard/qwiz/{qwiz}/revisions/{qwiz_revision}/revert",
 *     "revision_delete" =
 *     "/admin/qwizard/qwiz/{qwiz}/revisions/{qwiz_revision}/delete",
 *     "collection" = "/admin/qwizard/qwiz",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   bundle_entity_type = "qwiz_type",
 *   field_ui_base_route = "entity.qwiz_type.edit_form"
 * )
 */
class Qwiz extends RevisionableContentEntityBase implements QwizInterface {

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

    // If no revision author has been set explicitly, make the qwiz owner the
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
  public function getTimePerQuestion() {
    return $this->get('time_per_question')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTimePerQuestion(int $seconds) {
    $this->set('time_per_question', $seconds);
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
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * Gets the course id on the qwiz.
   *
   * @return mixed
   */
  public function getCourseId() {
    return $this->get('course')->target_id;
  }

  /**
   * Gets the course on the qwiz.
   *
   * @return mixed
   */
  public function getCourse() {
    return $this->course->entity;
  }

  /**
   * Sets the course id on the qwiz.
   *
   * @param $id
   *
   * @return $this
   */
  public function setCourseId($id) {
    $this->set('course', $id);
    return $this;
  }

  /**
   * Gets the Qwizard Class taxonomy term on the qwiz.
   *
   * @return mixed
   */
  public function getClass() {
    return $this->get('class')->entity;
  }

  /**
   * Gets the Qwizard Class id on the qwiz.
   *
   * @return mixed
   */
  public function getClassId() {
    return $this->get('class')->target_id;
  }

  /**
   * Sets the Qwizard Class id on the qwiz.
   *
   * @param $id
   *
   * @return $this
   */
  public function setClassId($id) {
    $this->set('class', $id);
    return $this;
  }

  /**
   * Gets the Qwizard Topics taxonomy term on the qwiz.
   *
   * @return mixed
   */
  public function getTopics() {
    return $this->get('topics')->entity;
  }

  /**
   * Gets the Qwizard Topics id on the qwiz.
   *
   * @return mixed
   */
  public function getTopicIds() {
    $ids = [];
    foreach ($this->get('topics')->getValue() as $id) {
      $ids[] = $id['target_id'];
    }
    return $ids;
  }

  /**
   * Sets the Qwizard Topics id on the qwiz.
   *
   * @param $id
   *
   * @return $this
   */
  public function setTopicId($id) {
    $this->set('topics', $id);
    return $this;
  }

  /**
   * Gets the pool type for this quiz.
   *
   * @return mixed
   */
  public function getPoolType() {
    return $this->get('pool_type')->value;
  }

  /**
   * Sets the pool type for this quiz.
   *
   * @param $pool_type
   *
   * @return $this
   */
  public function setPoolType($pool_type) {
    $this->set('pool_type', $pool_type);
    return $this;
  }

  /**
   * Returns decrement settings for a Qwiz, to be used for scoring by QwPool
   */
  public function getQwPoolDecrSettings(){
    $qwiz_type = $this->getPoolType();
    $settings = [
      'decr_correct' => 1,
      'decr_wrong' => 0,
      'decr_skipped' => 0,
    ];
    if($qwiz_type == 'decr_correct'){
      $settings = [
        'decr_correct' => 1,
        'decr_wrong' => 0,
        'decr_skipped' => 0,
      ];
    }
    elseif($qwiz_type == 'decrements_answered') {
      $settings = [
        'decr_correct' => 1,
        'decr_wrong' => 1,
        'decr_skipped' => 0,
      ];
    }
    elseif($qwiz_type == 'decrements_all'){
      $settings = [
        'decr_correct' => 1,
        'decr_wrong' => 1,
        'decr_skipped' => 1,
      ];
    }
    elseif($qwiz_type == 'fixed'){
      $settings = [
        'decr_correct' => 0,
        'decr_wrong' => 0,
        'decr_skipped' => 0,
      ];
    }

    return $settings;
  }

  /**
   * Function to get questions in quiz.
   *
   * @param null $count
   *   Randomly queries and limits the number of questions returned.
   *   NOTE: This does not take into account pools.
   *
   * @return array|int
   */
  public function getQuestionIds($count = NULL) {
    $topics = $this->getTopicIds();
    $course = $this->getCourseId();
    $class  = $this->getClassId();

    // Get question types from config.
    // @todo, Should this be a setting per quiz?

    $config = \Drupal::config('qwizard.qwizardsettings');
    $qtypes = $config->get('question_types');

    #var_dump($qtypes); exit;
    #$qtypes = ['qw_simple_choice'];

    $nids = [];

    if(!empty($topics)){
      /*$query = \Drupal::entityQuery('node')
        ->condition('type', $qtypes, 'IN')
        ->condition('field_courses.entity.tid', $course)
        ->condition('field_topics.entity.tid', $topics, 'IN');
      if(!empty($class)){
        $query->condition('field_classes.entity.tid', $class);
      }
      if (!empty($count)) {
        // If not getting all questions, we want to randomly select.
        $query->addTag('sort_by_random');
        $query->range(0, $count);
      }
      $nids = $query->execute();*/

      $QwizardGeneral = \Drupal::service('qwizard.general');
      $params = [
        'course_id' => $course,
        'topics' => $topics
      ];
      if (!empty($count)) {
        $params['count'] = $count;
      }
      if(!empty($class)){
        $params['classes'] = [$class];
      }

      $nids = $QwizardGeneral->getTotalQuizzes($params);
    }

    return $nids;
  }

  /**
   * Returns the number of questions in this quiz.
   *
   * @return int|void
   */
  public function getQuestionCount() {
    return count($this->getQuestionIds());
  }

  /**
   * Function to get questions from a quiz based on pool and random.
   *
   * @param int  $num_of_questions
   * @param bool $randomize
   *
   * @return array|bool|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getTestQuestions($num_of_questions = 10, $alt_type = 'standard', $randomize = TRUE, $onlyMarked = false) {
    $class = $this->getClassId();

    // Get current pool of type for this qwiz.
    $pool = QwPool::getPoolForClass($class);
    if (empty($pool)) {
      return [];
    }
    if($onlyMarked){
      // If we are looking at marked questions, it doesn't matter if the user completed the question or not already
      $question_from_pool = $pool->getAllQuestions();
    } elseif($alt_type == 'standard' || $alt_type == 'normal') {
      $question_from_pool = $pool->getQuestionsAvailable();
    }
    else {
      $question_from_pool = $pool->getAlternativeQuestions($alt_type);
    }
    $all_test_questions = $this->getQuestionIds();
    $questions          = fasterArrayIntersect($question_from_pool, $all_test_questions);

    if($onlyMarked) {
      $questions = $all_test_questions;
      $marked_questions = QwizardGeneral::getMarkedQuestions(['status' => 1, 'type' => 'qw_flashcard']);
      if(count($marked_questions)) {
        $questions = fasterArrayIntersect($marked_questions, $questions);
      }

    }

    if ($randomize) {
      shuffle($questions);
    }

    if ($randomize) {
      shuffle($questions);
    }

    if (!empty($num_of_questions)) {
      $questions = array_slice($questions, 0, $num_of_questions);
    }

    return $questions;
  }

  /**
   * Initializes a qwiz result at start of quiz take.
   *
   * @param $length
   * @param $subscription_id
   *
   * @return \Drupal\Core\Entity\EntityInterface | NULL
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function startQwiz($length, $subscription_id, $alt_type = 'standard', $onlyMarked = false) {
    $user              = \Drupal::currentUser();
    $qwiz_result_store = \Drupal::entityTypeManager()
      ->getStorage('qwiz_result');
    $start_timestamp   = time();
    // This is a new test take, initialize the $qwiz_result.
    // Get a list of questions for this quiz from the user's pool.
    $questions    = $this->getTestQuestions($length, $alt_type, TRUE, $onlyMarked);

    if(count($questions) < $length && ($alt_type == 'standard' || $alt_type == 'normal')){
      // This can happen if pool count is off. Leads to a frustrating experience of being unable to finish
      // Rebuild the user's pool and try again
      $QWMaintenancePoolsOneUser = new QWMaintenancePoolsOneUser;
      $QWMaintenancePoolsOneUser->rebuildPools(\Drupal::currentUser()->id(), false, FALSE, FALSE, true, true, $this->getClass());
      $questions    = $this->getTestQuestions($length, $alt_type, TRUE, $onlyMarked);
    }

    // In case we didn't get the number of questions back we requested.
    $length = count($questions);

    $name = $user->getAccountName() . ' ' . substr($this->name->value, 0, 20) . ' ' . $alt_type . ' ' . $length . ' Qs ' . date('y-m-d H:i:s');
    $name = substr($name, 0, 100);

    $qrproperties = [
      'name'            => $name,
      'qwiz_id'         => $this->id->value,
      'qwiz_rev'        => $this->vid->value,
      'subscription_id' => $subscription_id,
      'course'          => $this->getCourseId(),
      'class'           => $this->getClassId(),
      'start'           => date('c', $start_timestamp),
      'total_questions' => $length,
      'seen'            => 0,
      'attempted'       => 0,
      'correct'         => 0,
    ];
    // If this is a marked q review, immediately set the end time.
    if ($alt_type == 'marked') {
      $qrproperties['end'] = date('c', $start_timestamp);
    }
    $qwiz_result  = $qwiz_result_store->create($qrproperties);
    // Create snapshot and add reference it.
    $snapshot_store  = \Drupal::entityTypeManager()
      ->getStorage('qwiz_snapshot');
    $questions_array = QwizSnapshot::buildSnapshotQuestionsArray($questions);
    $snapshot_array  = [
      'qwiz_id'              => $this->id->value,
      'qwiz_rev'             => $this->vid->value,
      'user_id'              => $user->id(),
      'subscription_id'      => $subscription_id,
      'last_question_viewed' => 0,
      'questions'            => $questions_array,
    ];
    $ss_value        = Json::encode($snapshot_array);
    $ss_init         = [
      'snapshot' => $ss_value,
    ];
    $snapshot        = $snapshot_store->create($ss_init);
    $snapshot->save();
    $qwiz_result->set('snapshot', $snapshot->id());
    $qwiz_result->save();
    \Drupal::logger('QwizSession')->notice('Test started ' . $name);
    return $qwiz_result;
  }

  /**
   * Determines if there is an active quiz session for this quiz.
   *
   * @param $subscription_id
   * @param $uid
   *
   * @return \Drupal\qwizard\Entity\QwizResult|NULL
   */
  public function hasActiveSession($subscription_id, $uid) {
    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('qwiz_id', $this->id())
      ->condition('user_id', $uid)
      ->condition('subscription_id', $subscription_id)
      ->notExists('end');

    $qrids = $query->execute();
    if (count($qrids) > 1) {
      // This is bad.
      // @todo: Handle better. Probably by setting the others to have an end time
      //throw new \Exception();
    }
    elseif (!empty($qrids)) {
      $qrid = reset($qrids);
      // Return active quiz result.
      $qwiz_result_storage = \Drupal::entityTypeManager()
        ->getStorage('qwiz_result');
      $qwiz_result         = $qwiz_result_storage->load($qrid);
      return $qwiz_result;
    }

    return;
  }

  /**
   * Fetches pool type for quiz id.
   *
   * @param $quiz_id
   *
   * @return mixed
   */
  public static function qwizGetPoolType($quiz_id) {
    $con   = \Drupal\Core\Database\Database::getConnection();
    $query = $con->select('qwiz', 'q');
    $query->fields('q', ['pool_type']);
    $query->condition('q.id', $quiz_id);
    $pooltype = $query->execute()->fetchField();
    return $pooltype;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Quiz entity.'))
      ->setRevisionable(TRUE)
      ->setCardinality(1)
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
      ->setDescription(t('The name of the Quiz entity.'))
      ->setRevisionable(TRUE)
      ->setCardinality(1)
      ->setSettings([
        'max_length'      => 50,
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

    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Description'))
      ->setDescription(t('The description of the Quiz entity.'))
      ->setRevisionable(TRUE)
      ->setCardinality(1)
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
      ->setRequired(FALSE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Quiz is published.'))
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

    $fields['time_per_question'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Seconds Per Question'))
      ->setDescription(t('The amount of time in seconds per question, this determines the total test time. Zero is untimed'))
      ->setCardinality(1)
      ->setDefaultValue(0)
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
      ->setCardinality(1)
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

    $fields['class'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Class'))
      ->setDescription(t('The class this test covers.'))
      ->setCardinality(1)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'classes' => 'classes',
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
          'size'              => '25',
          'autocomplete_type' => 'tags',
          'placeholder'       => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['topics'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Topics'))
      ->setDescription(t('The topics this test covers.'))
      ->setRevisionable(TRUE)
      ->setCardinality(-1)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting('handler_settings',
        [
          'target_bundles' => [
            'topics' => 'topics',
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
          'size'              => '255',
          'autocomplete_type' => 'tags',
          'placeholder'       => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $pool_types = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo('qwpool');
    $options    = [];
    foreach ($pool_types as $id => $label) {
      $options[$id] = $label['label'];
    }

    $fields['pool_type'] = BaseFieldDefinition::create("list_string")
      ->setSetting('allowed_values', $options)
      ->setLabel('Uses Pool Type')
      ->setRequired(true)
      ->setCardinality(1)
      ->setDisplayOptions('form', [
        'type'   => 'options_buttons',
        'weight' => 5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('jsonb')
      ->setLabel(t('Quiz Data'))
      ->setDescription(t('JSON data for quiz.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ]);

    return $fields;
  }

}
