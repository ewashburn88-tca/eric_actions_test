<?php

namespace Drupal\qwarchive\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\qwarchive\QwArchiveManager;
use Drupal\qwarchive\QwArchiveRecordManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides cron queue for user data archival.
 *
 * @QueueWorker(
 *   id = "qwarchive_process",
 *   title = @Translation("User data archival worker"),
 *   cron = {"time" = 60}
 * )
 */
class QwArchiveProcessQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The qwarchive manager.
   */
  protected QwArchiveManager $qwarchiveManager;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $qwarchiveRecordManager;

  /**
   * Constructs a QwArchiveProcessQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\qwarchive\QwArchiveManager $qwarchive_manager
   *   The qwarchive manager.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $qwarchive_record_manager
   *   The qwarchive record manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, QwArchiveManager $qwarchive_manager, QwArchiveRecordManager $qwarchive_record_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->qwarchiveManager = $qwarchive_manager;
    $this->qwarchiveRecordManager = $qwarchive_record_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('qwarchive.manager'),
      $container->get('qwarchive.record_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    // Mark record as in progress.
    $this->qwarchiveRecordManager->changeRecordStatus($uid, QwArchiveRecordManager::STATUS_IN_PROGRESS);

    $data_types = $data['data_types'];
    $process_results = [];

    $data_type_callbacks = $this->qwarchiveManager->getDataTypeCallbacks();

    foreach ($data_types as $type) {
      $callback = $data_type_callbacks[$type];
      try {
        $process_results[$type] = $this->qwarchiveManager->$callback($account);
      }
      catch (\Throwable $e) {
        $process_results[$type] = FALSE;
        $message = $e->getMessage();
        // The process is failed. Update status & continue.
        $this->qwarchiveRecordManager->markItemAsFailed($uid, $type, $message);
      }
    }

    $failed_processes = array_keys(array_filter($process_results, function ($v) {
      return $v === FALSE;
    }));

    if (!empty($failed_processes)) {
      // The archival process is not completed.
      $this->qwarchiveRecordManager->markArchivalAsFailed($uid, 'Failed: ' . implode(', ', $failed_processes));
    }
    else {
      // All processes are successful.
      $this->qwarchiveRecordManager->markArchivalAsCompleted($uid, 'Successful via cron.');
    }
  }

}
