<?php

namespace Drupal\qwarchive\Plugin\rest\resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\qwarchive\QwArchiveRecordManager;
use Drupal\qwarchive\QwArchiveRestoreManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a resource to restore user data.
 *
 * @RestResource(
 *   id = "user_restore_request_resource",
 *   label = @Translation("User Restore Request Resource"),
 *   uri_paths = {
 *     "create" = "/api-v1/qwarchive/restore"
 *   }
 * )
 */
class UserRestoreRequestResource extends ResourceBase {

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
   * The qwarchive restore manager.
   */
  protected QwArchiveRestoreManager $restoreManager;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $recordManager;

  /**
   * Constructs a new UserRestoreRequestResource instance.
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
   * @param \Drupal\qwarchive\QwArchiveRestoreManager $restore_manager
   *   The qwarchive restore manager.
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
    QwArchiveRestoreManager $restore_manager,
    QwArchiveRecordManager $record_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
    $this->time = $time;
    $this->restoreManager = $restore_manager;
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
      $container->get('qwarchive.restore_manager'),
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
    if (!$this->currentUser->hasPermission('create restore request via rest')) {
      $payload['resultText'] = 'You do not have permission to create restore requests.';
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
      $this->logger->warning('Security Event: User @uid attempted to restore data for user @target_uid', [
        '@uid' => $this->currentUser->id(),
        '@target_uid' => $user_id,
      ]);
      $payload['resultText'] = 'You can only restore your own data.';
      return new ResourceResponse($payload, Response::HTTP_FORBIDDEN);
    }

    try {
      // Check if request already exists.
      $existing_request = $this->recordManager->getRestoreRequest($user_id);
      if (!empty($existing_request)) {
        // We could throw ConflictHttpException or just return 400.
        // But maybe we should return the existing one?
        // Let's error if exists to avoid confusion.
        $payload['resultText'] = 'A restore request is already pending for this user.';
        return new ResourceResponse($payload, Response::HTTP_CONFLICT);
      }

      // Prepare data callbacks.
      $data_type_callbacks = $this->restoreManager->getDataTypeCallbacks();

      // Create restore request record (Queued).
      $this->recordManager->addRestoreRequest($user_id, 'rest_api', 'Requested via REST API');

      // Add to processing queue.
      $queue = $this->queueFactory->get('qwarchive_restore_process');
      $queue->createQueue();
      $job_data = [
        'uid' => $user_id,
        'data_type_callbacks' => $data_type_callbacks,
      ];
      $queue->createItem($job_data);

      // Get the created request.
      $record = $this->recordManager->getRestoreRequest($user_id);

      $payload = [
        'success' => TRUE,
        'resultText' => 'Restore request created successfully.',
        'data' => [
          'user_id' => $user_id,
          'operation' => 'restore',
          'timestamp' => $this->time->getRequestTime(),
          'record' => $record,
        ],
      ];

      return new ResourceResponse($payload, Response::HTTP_OK);
    }
    catch (\Exception $e) {
      $this->logger->error('Restore request failed for user @uid: @message', [
        '@uid' => $user_id,
        '@message' => $e->getMessage(),
      ]);
      $payload['resultText'] = 'Internal Server Error: ' . $e->getMessage();
      return new ResourceResponse($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

}
