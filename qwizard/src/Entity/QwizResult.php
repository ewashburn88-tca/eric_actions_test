<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwizard\QwStudentResultsHandler;
use Drupal\qwizard\QwStudentResultsHandlerInterface;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\UserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Defines the Quiz Results entity.
 *
 * @ingroup qwizard
 *
 * @ContentEntityType(
 *   id = "qwiz_result",
 *   label = @Translation("Quiz Results"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwizResultListBuilder",
 *     "views_data" = "Drupal\qwizard\Entity\QwizResultViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qwizard\Form\QwizResultForm",
 *       "add" = "Drupal\qwizard\Form\QwizResultForm",
 *       "edit" = "Drupal\qwizard\Form\QwizResultForm",
 *       "delete" = "Drupal\qwizard\Form\QwizResultDeleteForm",
 *     },
 *     "access" = "Drupal\qwizard\QwizResultAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwizResultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "qwiz_result",
 *   admin_permission = "administer quiz results entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/qwizard/qwiz_result/{qwiz_result}",
 *     "add-form" = "/admin/qwizard/qwiz_result/add",
 *     "edit-form" = "/admin/qwizard/qwiz_result/{qwiz_result}/edit",
 *     "delete-form" = "/admin/qwizard/qwiz_result/{qwiz_result}/delete",
 *     "collection" = "/admin/qwizard/qwiz_result",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   field_ui_base_route = "qwiz_result.settings"
 * )
 */
class QwizResult extends ContentEntityBase implements QwizResultInterface {

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
    $storage = \Drupal::entityTypeManager()->getStorage('user');
    $user = $storage->load($this->getOwnerId());
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscriptionId() {
    $subscription_id = NULL;
    if (!empty($this->get('subscription_id')->target_id)) {
      $subscription_id = $this->get('subscription_id')->target_id;
    }
    elseif (!empty($this->get('subscription_id')->value)) {
      $subscription_id = $this->get('subscription_id')->value;
    }

    return $subscription_id;
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
   * Gets the Quiz Results json.
   *
   * @param bool $loaded If true return loaded term.
   *
   * @return mixed|string
   *   Results as json.
   */
  public function getCourse($loaded = FALSE) {
    if ($loaded) {
      return $this->course->getValue()[0]['value'];
    }
    return $this->course->getValue()[0]['target_id'];
  }

  /**
   * Sets the Quiz Results.
   *
   * @param string|array $json
   *   The Quiz Results json string or array.
   */
  public function setCourse($course) {
    if ($course instanceof Term) {
      $course = $course->id();
    }
    $this->set('course', $course);
  }

  /**
   * Gets the class.
   */
  public function getClass($loaded = FALSE) {
    if ($loaded) {
      return $this->class->entity;
    }
    return $this->class->getValue()[0]['target_id'];
  }

  /**
   * Sets the Quiz Results.
   *
   * @param string|array $json
   *   The Quiz Results json string or array.
   */
  public function setClass($class) {
    if ($class instanceof Term) {
      $class = $class->id();
    }
    $this->set('class', $class);
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
   * {@inheritdoc}
   */
  public function getQuiz() {
    $storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    $qwiz = $storage->load($this->getQuizId());
    return $qwiz;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuizRev() {
    return $this->get('qwiz_rev')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuizId() {
    return $this->get('qwiz_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuizJSONLabel() {
    $qwiz_label = \Drupal::entityTypeManager()
      ->getStorage('qwiz')
      ->load($this->getQuizId())
      ->label();
    if (!empty($this->getClass())) {
      $class_label = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($this->getClass())
        ->label();
    }
    return $qwiz_label . '-' . $class_label;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuizId($qid) {
    $this->set('qwiz_id', $qid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuizRev($revision) {
    $this->set('qwiz_rev', $revision);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuiz(QwizInterface $quiz) {
    $this->set('qwiz_id', $quiz->id());
    $this->set('qwiz_rev', $quiz->vid());
    return $this;
  }

  /**
   * Get end time.
   *
   * @return mixed
   */
  public function getEndTime() {
    return $this->get('end')->value;
  }

  /**
   * Sets end time.
   *
   * @param $time
   *
   * @return $this
   * @throws \Exception
   */
  public function setEndTime($time) {
    $iso = QwizardGeneral::formatIsoDate($time);
    $this->set('reviewed', $iso);
    return $this;
  }

  /**
   * Get reviewed time.
   *
   * @return mixed
   */
  public function getReviewedTime() {
    return $this->get('reviewed')->value;
  }

  /**
   * Sets reviewed time.
   *
   * @param $time
   *
   * @return $this
   * @throws \Exception
   */
  public function setReviewedTime($time) {
    $iso = QwizardGeneral::formatIsoDate($time);
    $this->set('reviewed', $iso);
    return $this;
  }

  /**
   * Get Correct.
   *
   * @return mixed
   */
  public function getCorrect() {
    return $this->get('correct')->value;
  }

  /**
   * Get Attempted.
   *
   * @return mixed
   */
  public function getAttempted() {
    return $this->get('attempted')->value;
  }

  /**
   * Get Seen.
   *
   * @return mixed
   */
  public function getSeen() {
    return $this->get('seen')->value;
  }

  /**
   * Get Total Questions.
   *
   * @return mixed
   */
  public function getTotalQuestions() {
    return $this->get('total_questions')->value;
  }

  /**
   * Returns the snapshot of this as an array.
   *
   * @return array
   */
  public function getSnapshot() {
    return $this->snapshot->entity;
  }

  /**
   * Returns the snapshot of this as an ID
   *
   * @return array
   */
  public function getSnapshotId() {
    return $this->snapshot->target_id;
  }

  /**
   * Returns the snapshot of this as an array.
   *
   * @return array
   */
  public function getSnapshotArray() {
    $snapshot = $this->snapshot->entity;
    $ss_array = $snapshot->getSnapshotArray();
    return $ss_array;
  }

  /**
   * Sets the snapshot of this as an array.
   *
   */
  public function setSnapshot($snapshot) {
    if ($snapshot instanceof QwizSnapshot) {
      $this->set('snapshot', $snapshot->id());
    }
    else {
      $this->set('snapshot', $snapshot);
    }
  }

  /**
   * Gets pool for this quiz result.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getResultPool($only_published = 1) {
    $course = $this->getCourse();
    $class = $this->getClass();
    $sub_id = $this->getSubscriptionId();

    $query = \Drupal::entityQuery('qwpool')
      ->condition('user_id', $this->getOwnerId())
      ->condition('subscription_id', $sub_id)
      ->condition('class', $class)
      ->condition('course', $course)
      ->sort('status', 'DESC');;
    if ($only_published) {
      $query->condition('status', 1);
    }
    $pids = $query->execute();
    if (empty($pids)) {
      \Drupal::logger('QwizResult')
        ->error("No active pool found for course: $course, class: $class, sub: $sub_id");
      return NULL;
    }
    if (count($pids) > 1) {
      \Drupal::logger('QwizResult')
        ->error("More than one active pool found for course: $course, class: $class, sub: $sub_id");
    }
    $pid = reset($pids);
    $pool_storage = \Drupal::entityTypeManager()->getStorage('qwpool');
    $pool = $pool_storage->load($pid);
    return $pool;
  }

  /**
   * Records results from answering a single question.
   *
   * @param bool $chosen_ans_id
   * @param      $current_question_id
   * @param      $question_idx
   *
   * @return bool
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function scoreQuestion($chosen_ans_id, $current_question_id, $question_idx, $recalculate_results = TRUE) {
    $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
    $results_service = \Drupal::service('qwizard.student_results_handler');

    $snapshot = $this->snapshot->entity;
    $ss_array = $snapshot->getSnapshotArray();
    $total_questions = $this->getTotalQuestions();

    if (empty($total_questions)) {
      // @todo: this is a problem, shouldn't be empty - handle
      $e = new \Exception();
      \Drupal::logger('QwizResult')
        ->error('Scoring question failed because of bad qwiz_result (total_questions=' . $this->getTotalQuestions() . ' ' . $e->getTraceAsString());
    }
    if ($current_question_id == NULL) {
      // @todo: this is a problem, shouldn't be empty - handle
      $e = new \Exception();
      \Drupal::logger('QwizResult')
        ->error('Scoring question failed because of bad qwiz_result (no current_question_id), = ' . json_encode($current_question_id) . ' ' . $e->getTraceAsString());
    }

    // Increment and update stats.
    // Check if answered.
    if (!empty($chosen_ans_id)) {
      if ($chosen_ans_id == $ss_array["questions"][$question_idx]["correct_answer"]) {
        $ss_array['question_summary'][$current_question_id] = 'correct';
      }
      else {
        $ss_array['question_summary'][$current_question_id] = 'incorrect';
      }
    }
    else {
      $ss_array['question_summary'][$current_question_id] = 'skipped';
    }

    $correct = $attempted = 0;
    foreach ($ss_array['question_summary'] as $qid => $qsummary) {
      if ($qid !== '') {
        if ($qsummary != 'skipped') {
          ++$attempted;
        }
        if ($qsummary == 'correct') {
          ++$correct;
        }
      }
    }

    $seen = count($ss_array['question_summary']);
    $this->set('correct', $correct);
    $this->set('seen', $seen);
    $this->set('attempted', $attempted);
    $score_attempted = empty($correct) || empty($attempted) ? 0 : $correct / $attempted;
    $score_seen = empty($correct) || empty($seen) ? 0 : $correct / $seen;
    $score_all = empty($correct) || empty($total_questions) ? 0 : $correct / $total_questions;
    $this->set('score_attempted', $score_attempted);
    $this->set('score_seen', $score_seen);
    $this->set('score_all', $score_all);

    // Update the snapshot array.
    $ss_array['last_question_viewed'] = $question_idx;
    $ss_array['questions'][$question_idx]['chosen_answer'] = $chosen_ans_id;
    $snapshot->setSnapshot($ss_array);
    try {
      $snapshot->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('QwizResult')
        ->error('Failure on saving snapshot. ' . $e->getMessage());
      return FALSE;
    }

    try {
      $this->save();
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('QwizResult')
        ->error('Failure on saving QwizResult. ' . $e->getMessage());
      return FALSE;
    }
    // Update the user's pool.
    $pool = $this->getResultPool();
    if (empty($pool)) {
      \Drupal::logger('QwizResult')
        ->error('Pool not loaded for quiz result: ' . $this->id());
      return FALSE;
    }
    try {
      $pool->updatePoolStats($this);
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('QwizResult')
        ->error('Failure on updating Pools. ' . $e->getMessage());
      return FALSE;
    }
    // Update student results.
    $acct = $this->getOwner();
    $course = $this->getQuiz()->getCourse(TRUE);
    $class = $pool->getClass();

    $sub = $subscriptions_service->getCurrentSubscription($course, $acct->id());
    if ($recalculate_results && !empty($sub)) {
      $results_service->rebuildStudentResults($acct, $sub, $class);
      //\Drupal::logger('QwizResult')->error('Rebuilding results for '.$acct->id());
    }
    return TRUE;
  }

  /**
   * Closes out the result end.
   *
   * Any QwizResult that has an end time is considered complete.
   */
  public function endQwizResult($rebuild_results_after = FALSE) {
    // Just record end time.
    if (empty($this->getEndTime())) {
      $this->set('end', date('c', time()));
    }
    // Since quiz is completed, the question array is updated and any questions
    // not answered will be marked as 'skipped', so that during review all
    // answers will be indicated as complete.
    $snapshot = $this->snapshot->entity;
    $ss_array = $snapshot->getSnapshotArray();
    //@todo: Do we want to remove empty sessions? If so, we can do it here.
    foreach ($ss_array['questions'] as $idx => &$question) {
      // @todo: Make option to clear out unseen questions. ********************
      if (empty($question['chosen_answer'])) {
        $question['chosen_answer'] = NULL;
        // Also update the summary.
        $ss_array['question_summary'][$question['question_id']] = 'skipped';
      }
      unset($question['question_text']);
      unset($question['feedback']);
      unset($question['answers']);
    }
    $snapshot->setSnapshot($ss_array);
    $snapshot->save();
    $this->save();
    //$qwiz_result->save();
    \Drupal::logger('QwizSession')->notice('Test ended ' . $this->getName());

    // After ending a quiz, rebuild results
    if ($rebuild_results_after) {
      \Drupal::service('qwizard.general')
        ->rebuildResultsForUser($this->getOwner()->id(), TRUE);
      /*$results_service = \Drupal::service('qwizard.student_results_handler');
      $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
      $acct = $this->getOwner();
      $class = $this->getClass();
      $course = $this->getQuiz()->getCourse(TRUE);
      $sub = $subscriptions_service->getCurrentSubscription($course, $acct->id());
      $results_service->rebuildStudentResults($acct, $sub, $class);*/
    }
    return $this;
  }

  /**
   * Gets all qwiz_results for given qwiz and user.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface $qwiz
   * @param \Drupal\user\UserInterface           $account
   * @param null                                 $start_date
   * @param null                                 $end_date
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo     This should be moved to a quiz handler service. Entity class
   *           should not include static functions other than field defs.
   * @obsolete Replace with getAllResultsForUser().
   */
  public static function getAllResultsForQwiz(QwizInterface $qwiz, UserInterface $account, $start_date = NULL, $end_date = NULL) {
    // Get current sub.
    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('qwiz_id', $qwiz->id())
      ->condition('user_id', $account->id())
      ->condition('qwiz_rev', $qwiz->getLoadedRevisionId());

    if ($start_date) {
      $query->condition('end', $start_date, '>=');
    }
    if ($end_date) {
      $query->condition('start', $end_date, '>=');
    }

    $moduleHandler = \Drupal::service('module_handler');

    if ($moduleHandler->moduleExists('qwsubs')) {    // Get current sub.
      $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
      $subscription = $subscriptions_service->getCurrentSubscription($qwiz->getCourse());
      $query->condition('subscription_id', $subscription->id());
    }

    $qrids = $query->execute();
    $storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $qwiz_results = $storage->loadMultiple($qrids);
    return $qwiz_results;
  }

  /**
   * Gets all qwiz_results for given user.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface $qwiz
   * @param \Drupal\user\UserInterface           $account
   * @param null                                 $start_date
   * @param null                                 $end_date
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo This should be moved to a quiz handler service. Entity class should
   *       not include static functions other than field defs.
   */
  public static function getAllResultsForUser(UserInterface $account, QwizInterface $qwiz = NULL, $course = NULL, $start_date = NULL, $end_date = NULL) {

    $query = \Drupal::entityQuery('qwiz_result')
      ->condition('user_id', $account->id());
    if ($qwiz) {
      $query->condition('qwiz_id', $qwiz->id())
        ->condition('qwiz_rev', $qwiz->getLoadedRevisionId());
    }
    if ($start_date) {
      $query->condition('end', $start_date, '>=');
    }
    if ($end_date) {
      $query->condition('start', $end_date, '>=');
    }

    $moduleHandler = \Drupal::service('module_handler');

    // Get current sub if qwiz or course is provided & subs module is enabled.
    // If qwiz provided, get course from it, regardless if provided.
    if (!empty($qwiz)) {
      $course = $qwiz->getCourse();
    }
    if ($moduleHandler->moduleExists('qwsubs') && !empty($course)) {
      $subscriptions_service = \Drupal::service('qwsubs.subscription_handler');
      $subscription = $subscriptions_service->getCurrentSubscription($course);
      $query->condition('subscription_id', $subscription->id());
    }

    $qrids = $query->execute();
    $storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $qwiz_results = $storage->loadMultiple($qrids);
    return $qwiz_results;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Quiz Results entity.'))
      ->setSettings([
        'max_length' => 100,
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
      ->setDescription(t('The user ID of the Quiz Results entity.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['qwiz_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Quiz ID'))
      ->setDescription(t('The quiz ID of the Quiz Results entity.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['qwiz_rev'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Quiz Revision'))
      ->setDescription(t('The quiz revision ID of the Quiz Results entity.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['subscription_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Subscription ID'))
      ->setDescription(t('The students subscription id of the Quiz Results Entity entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'subscription')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 0,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'placeholder' => '',
        ],
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
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '10',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
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
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 3,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '25',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['start'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Start Date & Time'))
      ->setDescription(t('The start datetime (ISO) of the Quiz Results Entity entity.'))
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
      ->setDescription(t('The end datetime (ISO) of the Quiz Results Entity entity.'))
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

    $fields['reviewed'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Last Review Date & Time'))
      ->setDescription(t('The reviewed datetime (ISO) of the Quiz Results Entity entity.'))
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

    $fields['score_attempted'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Score of Attempted Questions'))
      ->setDescription(t('Quiz Score (the grade) of questions attempted (answered correct or not.'))
      ->setSettings([
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['score_seen'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Score of Seen Questions'))
      ->setDescription(t('Quiz Score (the grade) of questions seen (viewed by student).'))
      ->setSettings([
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['score_all'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Score of All Test Questions'))
      ->setDescription(t('Quiz Score (the grade) of all questions on test.'))
      ->setSettings([
        'precision' => 5,
        'scale' => 2,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_decimal',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['total_questions'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Total Questions'))
      ->setDescription(t('Total number of questions in the quiz.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['attempted'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Attempted'))
      ->setDescription(t('Total number of questions attempted in the quiz. Student answered, write or wrong.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['seen'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Seen'))
      ->setDescription(t('Total number of questions seen in the quiz. Counted if question was viewed.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['correct'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Correct'))
      ->setDescription(t('Total number of questions correctly answered in the quiz.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['snapshot'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Quiz Snapshot'))
      ->setDescription(t('The snapshot id of the Quiz Results Entity entity.'))
      ->setSetting('target_type', 'qwiz_snapshot')
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

    return $fields;
  }

}
