<?php

namespace Drupal\qwarchive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Drupal\qwarchive\QwArchiveRecordManager;
use Drupal\qwarchive\QwArchiveRestoreBatch;
use Drupal\qwarchive\QwArchiveRestoreManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * QWArchive restore callbacks.
 */
class QwArchiveRestore extends ControllerBase {

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $qwArchiveRecordManager;

  /**
   * The qwarchive restore manager.
   */
  protected QwArchiveRestoreManager $qwRestoreManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->queueFactory = $container->get('queue');
    $instance->qwArchiveRecordManager = $container->get('qwarchive.record_manager');
    $instance->qwRestoreManager = $container->get('qwarchive.restore_manager');

    return $instance;
  }

  /**
   * Add tag to the question.
   */
  public function restore($account, $type) {
    if (is_numeric($account)) {
      $account = $this->entityTypeManager()->getStorage('user')->load($account);
    }

    $data_type_callbacks = $this->qwRestoreManager->getDataTypeCallbacks();
    if ($type == 'cron') {
      // Create a cron queue.
      $queue_name = 'qwarchive_restore_process';
      $queue = $this->queueFactory->get($queue_name);
      $queue->createQueue();
      $data = [
        'uid' => $account->id(),
        'data_type_callbacks' => $data_type_callbacks,
      ];
      $queue->createItem($data);
    }
    if ($type == 'batch') {
      $operations = [];
      foreach ($data_type_callbacks as $data_type => $callback) {
        $operations[] = [
          QwArchiveRestoreBatch::class . '::restoreUserData', [$account->id(), $data_type],
        ];
      }
      $batch = [
        'title' => $this->t('Restoring user data...'),
        'operations' => $operations,
        'finished' => QwArchiveRestoreBatch::class . '::restoreUserDataFinished',
        'init_message' => $this->t('Preparing to restore the user data...'),
        'progress_message' => $this->t('Processed @current out of @total...'),
        'batch_redirect' => Url::fromRoute('qwarchive.qw_archive_list'),
      ];
      batch_set($batch);
      return batch_process(Url::fromRoute('qwarchive.qw_archive_list'));
    }

    $url = Url::fromRoute('qwarchive.qw_archive_list');
    return new RedirectResponse($url->toString());
  }

}
