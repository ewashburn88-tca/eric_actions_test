<?php

namespace Drupal\qwizard\Entity;

use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\qwizard\ClassesHandler;
use Drupal\qwizard\CourseHandler;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\UserInterface;
use \Drupal\Component\Serialization\Json;
use \Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Defines the Question Pool entity.
 *
 * @ingroup qwizard
 *
 * @ContentEntityType(
 *   id = "qwpool",
 *   label = @Translation("Question Pool"),
 *   bundle_label = @Translation("Question Pool type"),
 *   handlers = {
 *     "storage" = "Drupal\qwizard\QwPoolStorage",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\qwizard\QwPoolListBuilder",
 *     "views_data" = "Drupal\qwizard\Entity\QwPoolViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\qwizard\Form\QwPoolForm",
 *       "add" = "Drupal\qwizard\Form\QwPoolForm",
 *       "edit" = "Drupal\qwizard\Form\QwPoolForm",
 *       "delete" = "Drupal\qwizard\Form\QwPoolDeleteForm",
 *     },
 *     "access" = "Drupal\qwizard\QwPoolAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\qwizard\QwPoolHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "qwpool",
 *   revision_table = "qwpool_revision",
 *   revision_data_table = "qwpool_field_revision",
 *   admin_permission = "administer question pool entities",
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
 *     "canonical" = "/admin/qwizard/qwpool/{qwpool}",
 *     "add-page" = "/admin/qwizard/qwpool/add",
 *     "add-form" = "/admin/qwizard/qwpool/add/{qwpool_type}",
 *     "edit-form" = "/admin/qwizard/qwpool/{qwpool}/edit",
 *     "delete-form" = "/admin/qwizard/qwpool/{qwpool}/delete",
 *     "version-history" = "/admin/qwizard/qwpool/{qwpool}/revisions",
 *     "revision" =
 *     "/admin/qwizard/qwpool/{qwpool}/revisions/{qwpool_revision}/view",
 *     "revision_revert" =
 *     "/admin/qwizard/qwpool/{qwpool}/revisions/{qwpool_revision}/revert",
 *     "revision_delete" =
 *     "/admin/qwizard/qwpool/{qwpool}/revisions/{qwpool_revision}/delete",
 *     "collection" = "/admin/qwizard/qwpool",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 *   bundle_entity_type = "qwpool_type",
 *   field_ui_base_route = "entity.qwpool_type.edit_form"
 * )
 */
class QwPool extends RevisionableContentEntityBase implements QwPoolInterface {

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

    // If no revision author has been set explicitly, make the qwpool owner the
    // revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }

    // Initialize questions if this is a new pool.
    if ($this->isNew()) {
      $this->initializeQuestions();
    }
    else {
      $complete  = count($this->getQuestionsCompleted());
      $this->setComplete($complete);
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
   * Gets the Question Pool course.
   *
   * @return string
   *   Course of the Question Pool.
   */
  public function getCourse() {
    return $this->get('course')->entity;
  }

  /**
   * Gets the Qwizard Class id on the qwiz.
   *
   * @return mixed
   */
  public function getCourseId() {
    return $this->get('course')->target_id;
  }

  /**
   * Sets the Question Pool course.
   *
   * @param string $course
   *   The Question Pool course.
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface
   *   The called Question Pool entity.
   */
  public function setCourse($id) {
    $this->set('course', $id);
    return $this;
  }

  /**
   * Sets the Question Pool status.
   *
   * @return string $status
   *   Status of the Question Pool.
   */
  public function setStatus($status) {
    $this->set('status', $status);
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
   * Gets the pool type (entity type).
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface|null
   */
  public function getPoolType() {
    return $this->bundle();
  }

  public function getQuestionCount() {
    $question_count = $this->total_questions->value;

    // If total questions could not be gotten, try rebuilding the pool
    if(empty($question_count)){
      $class     = $this->class->entity;
      $QwizardGeneral = \Drupal::service('qwizard.general');
      $params = ['class' => $class->id()];
      $questions = $QwizardGeneral->getTotalQuizzes($params);
      $this->setQuestionCount(count($questions));
    }

    return $question_count;
  }

  public function setQuestionCount($number) {
    // @todo: Should or when should this count questions array?
    $this->set('total_questions', $number);
  }

  public function getComplete() {
    return $this->complete->value;
  }

  /**
   * Sets complete count. Optionally verifies count against questions array.
   *
   * @param      $number
   * @param bool $verify
   *
   * @return bool
   */
  protected function setComplete($number, $verify = FALSE) {
    if ($verify) {
      $questions      = $this->getQuestionsArray();
      $count_complete = count($questions['complete']);
      if ($number != $count_complete) {
        return FALSE;
      }
    }
    $this->set('complete', $number);
    return TRUE;
  }

  /**
   * Gets the pool Questions array.
   *
   * @return array
   */
  public function getQuestionsArray() {
    $questions = $this->questions->value;
    if (empty($questions)) {
      return [];
    }
    return Json::decode($questions);
  }

  /**
   * Gets the Quiz Questions json.
   *
   * @return string
   *   Questions as json.
   */
  public function getQuestionsJson() {
    return $this->questions->value;
  }

  /**
   * Sets the Quiz Questions.
   *
   * @param string|array $json
   *   The Quiz Questions json string or array.
   */
  public function setQuestionsJson($json) {
    if (is_array($json)) {
      $json = Json::encode($json);
    }
    $this->set('questions', $json);
  }

  /**
   * Returns the questions left in the pool.
   *
   * What is returned depends on the decrement setting. If set, only incomplete
   * questions are returned, if not set all pool questions are returned.
   *
   * @return array
   */
  public function getQuestionsUnanswered() {
    $json      = $this->questions->value;
    $questions = Json::decode($json);
    if ($this->decrement) {
      return $questions['incomplete'];
    }
    return array_merge($questions['incomplete'], $questions['complete']);
  }

  /**
   * Returns all questions in the pool.
   *
   * @return array
   */
  public function getAllQuestions() {
    $json      = $this->questions->value;
    $questions = Json::decode($json);
    return array_merge($questions['complete'], $questions['incomplete']);
  }

  /**
   * Returns questions left in the pool (Unanswered).
   *
   * Alias of getQuestionsUnanswered().
   *
   * @return array
   */
  public function getQuestionsAvailable() {
    return $this->getQuestionsUnanswered();
  }

  /**
   * Retrieves a list of alternative questions, like MMQ (my missed questions).
   *
   * @param $altType
   *
   * @return array
   */
  public function getAlternativeQuestions($altType) {
    $altQuestions   = [];
    $questionsArray = $this->getQuestionsArray();
    switch ($altType) {
      case 'msq':
        // My skipped questions.
        /*$altQuestions = $questionsArray['skipped'];
        break;*/
      case 'mmq':
        // My missed questions.
        $altQuestions = $questionsArray['missed'];
        break;
      case 'marked':
        // @todo: Return list of unanswered marked questions for a take session.
        // @todo: Currently returning a closed session of all the marked questions.
        // Get all questions form pool, then intersect with marked.
        $pool_questions = $this->getAllQuestions();
        $marked = QwizardGeneral::getMarkedQuestions();
        $altQuestions = fasterArrayIntersect($pool_questions, $marked);
        break;
    }
    return empty($altQuestions) ? [] : $altQuestions;;
  }

  /**
   * Returns the question left in the pool.
   *
   * @return array
   */
  public function getQuestionsCompleted() {
    $json      = $this->questions->value;
    $questions = Json::decode($json);
    return $questions['complete'];
  }

  /**
   * Sets the questions array for the pool.
   *
   * @param array $questions
   *
   * @throws \Exception
   */
  public function setQuestions(array $questions) {
    if (!isset($questions['complete']) || !is_array($questions['complete'])) {
      throw new \Exception('Pool Questions [complete] must be set and an array.');
    }
    if (!isset($questions['incomplete']) || !is_array($questions['incomplete'])) {
      throw new \Exception('Pool Questions [incomplete] must be set and an array.');
    }
    if (!isset($questions['missed']) || !is_array($questions['missed'])) {
      throw new \Exception('Pool Questions [missed] must be set and an array.');
    }
    $complete  = count($questions['complete']);
    $questions = Json::encode($questions);
    $this->set('questions', $questions);
    $this->setComplete($complete);
  }

  /**
   * Resets questions in an existing pool.
   *
   * @todo: How to handle pool reset?
   */
  public function resetQuestions() {
    // @todo: Do we want to save past question settings.
    // @todo: Do we reset results? (don't confuse with subscription reset)
    $this->initializeQuestions();
  }

  /**
   * Initializes the question pool arrays.
   *
   * @throws \Exception
   */
  protected function initializeQuestions() {
    $class     = $this->class->entity;
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $params = ['class' => $class->id()];
    $questions = $QwizardGeneral->getTotalQuizzes($params);


    $this->setQuestionCount(count($questions));
    $this->setComplete(0);
    $questions_array = [
      'complete'   => [],
      'incomplete' => $questions,
      'missed'     => [],
      'skipped'    => [],
    ];

    $this->setQuestions($questions_array);

    //
  }

  /**
   * Given a quiz result, this will update the pool statistics.
   *
   * @param $qwiz_result
   *
   * @return \Drupal\qwizard\Entity\QwPoolInterface|void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function updatePoolStats($qwiz_result, $return_only__no_save = false) {
    // @todo: Do we need to verify $qwiz_result is for this pool?
    $ss_array        = $qwiz_result->getSnapshotArray();
    // There's no questions in the result, so just return.
    if (empty($ss_array['questions'])) return;

    $questions_array = $this->getQuestionsArray();

    foreach ($ss_array['questions'] as $idx => $qdata) {
      $qid = $qdata['question_id'];

      // See if node even exists in this class anymore. If not, ignore it
      // @todo leaving alone for now pending further testing
      /*$question_node_in_class = \Drupal::entityQuery('node')
        ->condition('nid', $qid)
        ->condition('field_classes', $this->getClassId())
        ->condition('field_courses', $this->getCourseId())
        ->execute();
      if(empty($question_node_in_class)){
        unset($questions_array['questions'][$idx]);
        continue;
      }*/


      if (!in_array($qid, $questions_array['complete']) && !in_array($qid, $questions_array['incomplete'])) {
        // @todo: This is a problem because this question doesn't belong to this
        // pool. We need to do something. Could be the result of a question
        // being added after pool creation, which is ok but needs handling or
        // could be that this questions was removed.
        if (qwizard_in_debug_mode()) {
          $class  = $qwiz_result->getClass(TRUE);
          $qwiz = $qwiz_result->getQuiz();
          $url    = Url::fromRoute('entity.node.edit_form', ['node' => $qid]);
          $q_link = Link::fromTextAndUrl($qid, $url);
          // @todo: output classes, topics and tags in log message.
          \Drupal::logger('QwPool')
            ->debug('Question ' . $q_link->toString() . ' may need updating for quiz  ' . $qwiz->name->value . ' in class ' . $class->name->value . ': taxonomy/term/' . $class->id());
        }
        // Just ignoring it. Unsure.
        continue;

        //throw new EntityMalformedException('Question doesn\'t belong to pool.');
      }
      // Set the status.
      if (empty($qdata['chosen_answer'])) {
        $status = 'skipped';
      }
      elseif ($qdata['chosen_answer'] == $qdata['correct_answer']) {
        $status = 'correct';
      }
      // They chose an answer that no longer exists in results array. Just mark it correct. Most likely an import issue.
      elseif(!empty($qdata['chosen_answer']) && !empty($qdata['answers']) && !in_array($qdata['chosen_answer'], array_keys($qdata['answers']))){
        $status = 'correct';
      }
      else $status = 'incorrect';

      // Check if complete is already checked off in pool.
      // @todo: Why not index arrays by qid and remove array_search.
      // Check pool type to see how to decrement.
      // Use the quiz's setting. The ones on qwpool are incorrect. Better to trust the quiz and ignore the QwPool entity.
      /*$decr_correct = $this->decrement->value;
      $decr_wrong = $this->decr_wrong->value;
      $decr_skipped = $this->decr_skipped->value;*/
      $decr_settings = $qwiz_result->getQuiz()->getQwPoolDecrSettings();
      $decr_correct = $decr_settings['decr_correct'];
      $decr_wrong = $decr_settings['decr_wrong'];
      $decr_skipped = $decr_settings['decr_skipped'];


      if ($status == 'correct') {
        unset($questions_array['missed'][$qid]);
        unset($questions_array['skipped'][$qid]);
        if (empty($questions_array['correct'])) $questions_array['correct'] = [];
        if (!in_array($qid, $questions_array['correct'])) {
          $questions_array['correct'][$qid] = $qid;
        }
        if ($decr_correct) {
          $this->mark_complete($qid, $questions_array);
        }
      }
      // Count missed questions, save in array for MMQ.
      elseif ($status == 'incorrect') {
        unset($questions_array['skipped'][$qid]);
        if (!in_array($qid, $questions_array['missed'])) {
          $questions_array['missed'][$qid] = $qid;
        }
        if ($decr_wrong) {
          $this->mark_complete($qid, $questions_array);
        }
      }
      // Count skipped questions, save in array for MSQ.
      elseif ($status == 'skipped' && !in_array($qid, $questions_array['skipped'])) {
        $questions_array['skipped'][$qid] = $qid;
        if ($decr_skipped) {
          $this->mark_complete($qid, $questions_array);
        }
      }
    }
    $this->setQuestions($questions_array);

    if($return_only__no_save){
      return $this;
    }
    else {
      $this->save();
    }
  }

  private function mark_complete($qid, &$questions_array) {
    if (!in_array($qid, $questions_array['complete'])) {
      $questions_array['complete'][] = $qid;
      if (($key = array_search($qid, $questions_array['incomplete'])) !== FALSE) {
        unset($questions_array['incomplete'][$key]);
      }
    }
  }

  /**
   * Returns the qwiz ids or objects associated with the pool.
   *
   * @param bool $loaded
   *
   * @return array|\Drupal\Core\Entity\EntityInterface[]|int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQwizzesInPool($loaded = FALSE) {
    $query = \Drupal::entityQuery('qwiz')
      ->condition('course', $this->getCourseId())
      // Removed, we don't care about poolTypes on qwpool entries
      //->condition('pool_type', $this->getPoolType())
      ->condition('status', 1);
    if ($class = $this->getClassId()) {
      $query->condition('class', $class);
    }
    $qids = $query->execute();

    if ($loaded) {
      $storage = \Drupal::entityTypeManager()->getStorage('qwiz');
      return $storage->loadMultiple($qids);
    }
    return $qids;
  }

  /**
   * Returns number of questions in the pool for a particular quiz.
   *
   * @param \Drupal\qwizard\Entity\Qwiz $qwiz
   *
   * @return int|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQuestionsByQwiz(Qwiz $qwiz) {
    $questions = $qwiz->getQuestionIds();
    return empty($questions) ? [] : $questions;
  }

  /**
   * Returns number of questions in the pool for a particular quiz.
   *
   * @param \Drupal\qwizard\Entity\Qwiz $qwiz
   *
   * @return int|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQuestionCountByQwiz(Qwiz $qwiz) {
    return count($this->getQuestionsByQwiz($qwiz));
  }

  /**
   * Returns number of correct questions in the pool for a particular quiz.
   *
   * @param \Drupal\qwizard\Entity\Qwiz $qwiz
   *
   * @return int|void
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCompleteCountByQwiz(Qwiz $qwiz) {
    $questions               = $this->getQuestionsByQwiz($qwiz);
    $questions_complete      = $this->getQuestionsCompleted();
    $qwiz_questions_complete = fasterArrayIntersect($questions, $questions_complete);

    return count($qwiz_questions_complete);
  }

  /**
   * Returns current pool for given class and user.
   *
   * @param      $class_id
   * @param null $uid
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo This should be moved to a quiz handler service. Entity class should
   *        not include static functions other than field defs.
   */
  public static function getPoolForClass($class_id, $uid = NULL, $subscription_id = NULL, $include_inactive = false) {
    if ($uid == NULL) {
      $account = \Drupal::currentUser();
      $uid     = $account->id();
    }
    if ($subscription_id == NULL) {
      $course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
      $subscription = SubscriptionHandler::getCurrentSubscription($course, $uid);
      if(!empty($subscription)) {
        $subscription_id = $subscription->id();
      }
    }

    $query = \Drupal::entityQuery('qwpool')
      ->condition('class', $class_id)
      ->condition('user_id', $uid)
      ->sort('id', 'ASC');

    if(!$include_inactive){
      $query->condition('status', 1);
    }

    if (isset($subscription_id)) {
      $query->condition('subscription_id', $subscription_id);
    }
    $pids = $query->execute();
    if(empty($pids)) return null;

    // Should only be one.
    if(count($pids) > 1){
      //\Drupal::logger('qwizard')->notice('getPoolForClass returned more than 1 item for user '.$uid.' and course '.$class_id.' and class '.$class_id);
    }
    $pid        = reset($pids);
    $pool_store = \Drupal::entityTypeManager()->getStorage('qwpool');
    try {
      $pool = $pool_store->load($pid);
      if (empty($pool)) {
        \Drupal::logger('qwizard')->notice('getPoolForClass could not load a pool from a $pid');
      }
    }
    catch (EntityStorageException $e) {
      throw new HttpException(500, 'Internal Server Error in getPoolForClass', $e);
    }
    return $pool;
  }

  /**
   * Returns current pool for given class and user.
   *
   * @param $uid
   * @param $subscription_id
   * @param $include_inactive
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @todo This should be moved to a quiz handler service. Entity class should
   *         not include static functions other than field defs.
   */
  public static function getPoolsForUser($uid, $subscription_id = NULL, $include_inactive = FALSE, $return_loaded = FALSE) {
    $query = \Drupal::entityQuery('qwpool')
      ->condition('user_id', $uid)
      ->sort('id', 'ASC');

    if(!$include_inactive){
      $query->condition('status', 1);
    }

    if (isset($subscription_id)) {
      $query->condition('subscription_id', $subscription_id);
    }

    $pools = $query->execute();
    if(empty($pools)) return null;

    if ($return_loaded) {
      $pool_store = \Drupal::entityTypeManager()->getStorage('qwpool');
      try {
        $pools = $pool_store->loadMultiple($pools);
        if (empty($pools)) {
          \Drupal::logger('qwizard')
            ->notice('getPoolForClass could not load a pools');
        }
      }
      catch (EntityStorageException $e) {
        throw new HttpException(500, 'Internal Server Error in getPoolForClass', $e);
      }
    }
    return $pools;
  }

  /**
   * Updates the pool statistics for the given quiz result.
   *
   * @param $pool_id
   * @param $result_id
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see updatePoolStats()
   */
  public static function recordPoolStats($result_id, $pool_id) {

    $result_storage = \Drupal::entityTypeManager()->getStorage('qwiz_result');
    $result         = $result_storage->load($result_id);
    $pool_storage   = \Drupal::entityTypeManager()->getStorage('qwpool');
    $pool           = $pool_storage->load($pool_id);

    $pool->updatePoolStats($result);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Question Pool entity.'))
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

    $fields['subscription_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subscription ID'))
      ->setDescription(t('The students subscription id of the Quiz Results Entity entity.'))
      ->setSettings([
        'max_length'      => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue(1)
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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Question Pool entity.'))
      ->setRevisionable(TRUE)
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

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Question Pool is published.'))
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

    $fields['decrement'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Decrement'))
      ->setDescription(t('A boolean indicating whether the Question Pool should deplete as questions are answered.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type'   => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['decr_wrong'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Decrement if incorrect'))
      ->setDescription(t('If true decrements by incorrect questions, false by seen.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type'   => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['decr_skipped'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Decrement if skipped'))
      ->setDescription(t('If true decrements by skipped questions, false by seen.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type'   => 'boolean_checkbox',
        'weight' => -3,
      ]);

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setDescription(t('The course of the Question Pool entity.'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting(
        'handler_settings',
        [
          'target_bundles' => [
            'courses' => 'courses',
          ],
        ]
      )
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
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler', 'default:taxonomy_term')
      ->setSetting(
        'handler_settings',
        [
          'target_bundles' => [
            'classes' => 'classes',
          ],
        ]
      )
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

    $fields['total_questions'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Total Questions'))
      ->setDescription(t('Total number of questions in the quiz.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['complete'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Complete'))
      ->setDescription(t('Total number of questions completed in the pool.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ])
      ->setDisplayOptions('form', [
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['questions'] = BaseFieldDefinition::create('jsonb')
      ->setLabel(t('Questions in Pool'))
      ->setDescription(t('JSON Array of all question nids divided by status [incomplete, complete].'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }
}
