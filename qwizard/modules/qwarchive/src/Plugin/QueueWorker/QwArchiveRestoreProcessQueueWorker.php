<?php

namespace Drupal\qwarchive\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\qwarchive\QwArchiveRecordManager;
use Drupal\qwarchive\QwArchiveRestoreManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides cron queue for user data restore.
 *
 * @QueueWorker(
 *   id = "qwarchive_restore_process",
 *   title = @Translation("User data restore worker"),
 *   cron = {"time" = 60}
 * )
 */
class QwArchiveRestoreProcessQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The qwarchive restore manager.
   */
  protected QwArchiveRestoreManager $qwarchiveRestoreManager;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $qwarchiveRecordManager;

  /**
   * Constructs a QwArchiveRestoreProcessQueueWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\qwarchive\QwArchiveRestoreManager $qwarchive_restore_manager
   *   The qwarchive manager.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $qwarchive_record_manager
   *   The qwarchive record manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LoggerInterface $logger, QwArchiveRestoreManager $qwarchive_restore_manager, QwArchiveRecordManager $qwarchive_record_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->qwarchiveRestoreManager = $qwarchive_restore_manager;
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
      $container->get('logger.factory')->get('qwarchive'),
      $container->get('qwarchive.restore_manager'),
      $container->get('qwarchive.record_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $uid = $data['uid'];
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    $data_type_callbacks = $data['data_type_callbacks'];
    $process_results = [];

    foreach ($data_type_callbacks as $type => $callback) {
      try {
        $process_results[$type] = $this->qwarchiveRestoreManager->$callback($account);
      }
      catch (\Throwable $e) {
        $process_results[$type] = FALSE;
        $message = $e->getMessage();
        $this->logger->error("Data restore for user $uid for $type failed with error: $message");
      }
    }

    $failed_processes = array_keys(array_filter($process_results, function ($v) {
      return $v === FALSE;
    }));

    if (!empty($failed_processes)) {
      // The archival process is not completed.
      $failures = implode(', ', $failed_processes);
      $this->logger->error("Data restore processed failed for user $uid: $failures");
    }
    else {
      // All processes are successful.
      $this->qwarchiveRecordManager->removeRecord($uid, TRUE);
    }
  }

}
