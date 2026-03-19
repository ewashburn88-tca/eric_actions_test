<?php

namespace Drupal\qwmaintenance;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\qwizard\Entity\QwPool;
use Drupal\qwizard\MergedQwizInterface;
use Drupal\qwizard\QwizardGeneral;
use Drupal\qwizard\QwizardGeneralInterface;
use Drupal\qwsubs\SubscriptionHandlerInterface;

/**
 * Provide pool maintenance methods.
 */
class PoolsMaintenanceService implements PoolsMaintenanceServiceInterface {

  use MessengerTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Quiz wizard general service.
   *
   * @var \Drupal\qwizard\QwizardGeneralInterface
   */
  protected $qwizardGeneral;

  /**
   * The merged quiz service.
   *
   * @var \Drupal\qwizard\MergedQwizInterface
   */
  protected $mergedQwiz;

  /**
   * The subscription handler.
   *
   * @var \Drupal\qwsubs\SubscriptionHandlerInterface
   */
  protected $subscriptionHandler;

  /**
   * The user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The course term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $course;

  /**
   * The class term.
   *
   * @var int
   */
  protected $class;

  /**
   * Constructs new PoolsMaintenanceService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\qwizard\QwizardGeneralInterface $qwizard_general
   *   The entity type manager.
   * @param \Drupal\qwizard\MergedQwizInterface $merged_qwiz
   *   The merged quiz service.
   * @param \Drupal\qwsubs\SubscriptionHandlerInterface $subscription_handler
   *   The subscription handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QwizardGeneralInterface $qwizard_general, MergedQwizInterface $merged_qwiz, SubscriptionHandlerInterface $subscription_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->qwizardGeneral = $qwizard_general;
    $this->mergedQwiz = $merged_qwiz;
    $this->subscriptionHandler = $subscription_handler;
  }

  /**
   * Sets the user account.
   *
   * @param int $uid
   *   The user id of user to set.
   */
  public function setUser($uid) {
    $this->account = $this->entityTypeManager->getStorage('user')->load($uid);
  }

  /**
   * Sets the class.
   *
   * @param int $class_id
   *   The term id of class term to set.
   */
  public function setClass($class_id) {
    $this->class = $class_id;
  }

  /**
   * Rebuilds question pools.
   *
   * @param bool $only_active
   *   Rebuild only active. Unused - remove if not required.
   * @param bool $secondary_classes_only
   *   Flag to indicate if only non-primary classes should be rebuilt.
   * @param bool $delete_pools_first
   *   Flag to indicate if pools should be deleted before rebuild.
   * @param bool $active_subscriptions_only
   *   Flag to indicate if only active subscriptions should be loaded.
   * @param bool $enable_messaging
   *   Flag to indicate if log entries should be added.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function rebuildQuestionPools($only_active = TRUE, $secondary_classes_only = FALSE, $delete_pools_first = TRUE, $active_subscriptions_only = TRUE, $enable_messaging = FALSE) {
    // This my take a while.
    set_time_limit(240);
    // Get our static data.
    $statics = $this->qwizardGeneral->getStatics();

    // Load up the storages to use later.
    $qwpool_storage = $this->entityTypeManager->getStorage('qwpool');
    $pooltype_storage = $this->entityTypeManager->getStorage('qwpool_type');
    $qwiz_result_storage = $this->entityTypeManager->getStorage('qwiz_result');

    // Get active subscriptions for user for provided course.
    $subscriptions = $this->subscriptionHandler->getUserSubscriptions($this->account->id(), $this->course, $active_subscriptions_only);

    // Prepare an array of class term ids.
    if (empty($this->class)) {
      $classes = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('classes', 0, NULL, TRUE);
    }
    else {
      $classes = [$this->class];
    }

    // Sometimes we only want to rebuild non-primary classes from QWMaintenance.
    if ($secondary_classes_only) {
      foreach ($classes as $key => $value) {
        $class_id = $value->id();
        if (in_array($class_id, $statics['study_test_classes'])) {
          unset($classes[$key]);
        }
      }
    }

    // Remove unpublished classes.
    foreach ($classes as $key => $value) {
      if (empty($value->status->value)) {
        unset($classes[$key]);
      }
    }

    if (!empty($subscriptions)) {
      // @todo Right now delete all user's pools, this can be changed back
      // after pool issues settle down.
      if ($delete_pools_first) {
        $query = $qwpool_storage->getQuery();
        $query->condition('user_id', $this->account->id());
        if (!empty($this->class)) {
          $query->condition('class', $this->class);
        }
        $qwpools = $query->execute();

        // Delete ALL pools with this subscription, not just the one we pulled.
        foreach ($qwpools as $pool_id) {
          $qwpool = $qwpool_storage->load($pool_id);
          if (!empty($qwpool)) {
            $qwpool->delete();
          }
        }
      }

      foreach ($subscriptions as $subscription) {
        // Get all qwiz results first, organize by class.
        $sub_course = $subscription->getCourseId();
        $query = $qwiz_result_storage->getQuery();
        $query->condition('user_id', $this->account->id());
        $query->condition('subscription_id', $subscription->id());
        $query->condition('course', $sub_course);
        if (!empty($this->class)) {
          $query->condition('class', $this->class);
        }

        $qwiz_results_ids = $query->execute();
        $questions_from_vids = [];
        $qwiz_results_by_class = [];
        if (!empty($qwiz_results_ids)) {
          $qwiz_results = $qwiz_result_storage->loadMultiple($qwiz_results_ids);
          // Preload the cache.
          $preload_data = $this->mergedQwiz->preloadMultipleSnapshotArraysFromQuizResults($qwiz_results);
          $questions_from_vids = $preload_data['questions_from_vids'];

          foreach ($qwiz_results as $qwiz_result) {
            $qwiz_results_by_class[$qwiz_result->getClass()][$qwiz_result->id()] = $qwiz_result;
          }
        }

        // Create a pool for each class.
        foreach ($classes as $class) {
          $class_course = $class->get('field_course')->target_id;
          if ($class_course == $sub_course) {
            // Check if the pool exists in D9 site. If not, create one.
            $field_pooltype = $class->get('field_pool_type')->getValue();

            $qwpool_id = NULL;
            $qwpool = NULL;

            if ($field_pooltype[0]['target_id']) {
              $query = $qwpool_storage->getQuery()
                ->condition('user_id', $this->account->id())
                ->condition('subscription_id', $subscription->id())
                ->condition('class', $class->id())
                ->condition('course', $sub_course)
                ->condition('status', $subscription->status->value);
              $qwpools = $query->execute();

              // Should only be one.
              $qwpool_id = reset($qwpools);

              if (!empty($qwpool_id) && $delete_pools_first) {
                // Delete ALL pools with this subscription,
                // not just the one we pulled.
                foreach ($qwpools as $pool_id) {
                  $qwpool = $qwpool_storage->load($pool_id);
                  $qwpool->delete();
                }
                $qwpool_id = NULL;
              }
            }

            if (empty($qwpool_id)) {
              // Create the pool.
              // Load pool type.
              $pooltype_entity = NULL;
              if (isset($field_pooltype[0]['target_id']) && $field_pooltype[0]['target_id'] != NULL) {
                $pooltype_entity = $pooltype_storage->load($field_pooltype[0]['target_id']);
              }

              $properties = [
                'name' => QwizardGeneral::shortNamesToFit($class->getName() . ' Pool', 50),
                'type' => $field_pooltype[0]['target_id'],
                'created' => $subscription->getCreated(),
                'changed' => $_SERVER['REQUEST_TIME'],
                'user_id' => $this->account->id(),
                'status' => $subscription->isActive(),
                'decrement' => ($pooltype_entity != NULL) ? $pooltype_entity->isDefaultDecrement() : NULL,
                'subscription_id' => $subscription->id(),
                'course' => $subscription->getCourseId(),
                'class' => $class->id(),
              ];
              $qwpool = $qwpool_storage->create($properties);
              $qwpool->save();
            }
            if (!($qwpool instanceof QwPool)) {
              $qwpool = $qwpool_storage->load($qwpool_id);
            }

            // Update total for this pool. updatePoolStats does not.
            $class_id = $qwpool->getClassId();
            if (!empty($class_id)) {
              $params = [
                'class' => $class_id,
              ];
            }
            else {
              // @todo this is meant to handle pools where class is null,
              // but it never actually gets called here.
              $course = $qwpool->getCourseId();
              $classes = [];
              $classes = array_merge($classes, $statics['test_classes'][$course]);
              $classes[] = array_merge($statics['study_classes'][$course]);

              $params = [
                'classes' => $classes,
              ];
            }

            $question_count = count($this->qwizardGeneral->getTotalQuizzes($params));
            $qwpool->setQuestionCount($question_count);
            $qwpool->save();

            if (!empty($qwiz_results_by_class[$class_id])) {
              $qwiz_results = $qwiz_results_by_class[$class_id];
              foreach ($qwiz_results as $qwiz_result) {
                $snapshot = $qwiz_result->getSnapshot();
                $snapshot->setPreloadedQuestionsByVID($questions_from_vids);
                try {
                  $qwpool->updatePoolStats($qwiz_result, true);
                }
                catch (EntityStorageException $e) {
                  if ($enable_messaging) {
                    $this->messenger()->addWarning($e->getMessage());
                  }
                  return $this;
                }
              }
              $qwpool->save();
              if ($enable_messaging) {
                $this->messenger()->addMessage('Updated pool for user=' . $this->account->id() . ' subscription=' . $subscription->id() . ' class=' . $class->id());
              }
            }
          }
        }
      }
    }
  }

}
