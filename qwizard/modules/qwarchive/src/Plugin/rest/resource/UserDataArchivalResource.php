<?php

namespace Drupal\qwarchive\Plugin\rest\resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\qwarchive\QwArchiveManager;
use Drupal\qwarchive\QwArchiveRecordManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a resource to archive user data.
 *
 * @RestResource(
 *   id = "user_data_archival_resource",
 *   label = @Translation("User Data Archival Resource"),
 *   uri_paths = {
 *     "create" = "/api-v1/qwarchive/archive"
 *   }
 * )
 */
class UserDataArchivalResource extends ResourceBase {

  /**
   * The current user.
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The time service.
   */
  protected TimeInterface $time;

  /**
   * The qwarchive manager.
   */
  protected QwArchiveManager $archiveManager;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $recordManager;

  /**
   * Constructs a new UserDataArchivalResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\qwarchive\QwArchiveManager $archive_manager
   *   The qwarchive manager.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $record_manager
   *   The qwarchive record manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    QueueFactory $queue_factory,
    TimeInterface $time,
    QwArchiveManager $archive_manager,
    QwArchiveRecordManager $record_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
    $this->time = $time;
    $this->archiveManager = $archive_manager;
    $this->recordManager = $record_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('qwarchive'),
      $container->get('current_user'),
      $container->get('queue'),
      $container->get('datetime.time'),
      $container->get('qwarchive.manager'),
      $container->get('qwarchive.record_manager')
    );
  }

  /**
   * Responds to POST requests.
   */
  public function post(Request $request) {
    $payload = [
      'success' => FALSE,
    ];

    // Check for permission.
    if (!$this->currentUser->hasPermission('create archival via rest')) {
      $payload['resultText'] = 'You do not have permission to create archival requests.';
      return new ResourceResponse($payload, Response::HTTP_FORBIDDEN);
    }

    $json_content = $request->getContent();
    $data = Json::decode($json_content);

    if (empty($data['user_id'])) {
      $payload['resultText'] = 'Missing required parameter user_id.';
      return new ResourceResponse($payload, Response::HTTP_BAD_REQUEST);
    }

    $user_id = $data['user_id'];

    // The user_id parameter must be numeric.
    if (!is_numeric($user_id)) {
      $payload['resultText'] = 'Invalid user ID parameter.';
      return new ResourceResponse($payload, Response::HTTP_BAD_REQUEST);
    }

    // User identity verification.
    if ((int) $this->currentUser->id() !== (int) $user_id) {
      $this->logger->warning('Security Event: User @uid attempted to archive data for user @target_uid', [
        '@uid' => $this->currentUser->id(),
        '@target_uid' => $user_id,
      ]);
      $payload['resultText'] = 'You can only archive your own data.';
      return new ResourceResponse($payload, Response::HTTP_FORBIDDEN);
    }

    try {
      // Prepare data types.
      $data_types = array_keys($this->archiveManager->getDataTypeCallbacks());

      if (empty($data_types)) {
        $payload['resultText'] = 'No data types available for archival.';
        return new ResourceResponse($payload, Response::HTTP_OK);
      }

      // Create record in DB (Queued).
      // Passing 'rest_api' as type to set the status to QUEUED.
      $this->recordManager->add($user_id, $data_types, 'rest_api');

      // Add to processing queue.
      $queue = $this->queueFactory->get('qwarchive_process');
      $queue->createQueue();
      $job_data = [
        'uid' => $user_id,
        'data_types' => $data_types,
      ];
      $queue->createItem($job_data);

      // Fetch the created records to return.
      // Get the latest archive record for this user.
      $conditions = [
        [
          'field' => 'uid',
          'value' => $user_id,
        ],
      ];
      // Limit to 1 to get the latest one.
      $records = $this->recordManager->getRecords($conditions, 1);
      $record = reset($records);

      $items = [];
      if ($record && isset($record['id'])) {
        $items = $this->recordManager->getRecordItems($record['id']);
      }

      $payload = [
        'success' => TRUE,
        'resultText' => 'Archival request created successfully.',
        'data' => [
          'user_id' => $user_id,
          'operation' => 'archive',
          'timestamp' => $this->time->getRequestTime(),
          'record' => $record,
          'items' => $items,
        ],
      ];

      return new ResourceResponse($payload, Response::HTTP_OK);

    }
    catch (\Exception $e) {
      $this->logger->error('Archival request failed for user @uid: @message', [
        '@uid' => $user_id,
        '@message' => $e->getMessage(),
      ]);
      $payload['resultText'] = 'Internal Server Error: ' . $e->getMessage();
      return new ResourceResponse($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

}
