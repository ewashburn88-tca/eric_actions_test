<?php

namespace Drupal\qwarchive\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Url;
use Drupal\qwarchive\QwArchiveBatch;
use Drupal\qwarchive\QwArchiveManager;
use Drupal\qwarchive\QwArchiveRecordManager;
use Drupal\qwarchive\QwArchiveUserManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create new archive form.
 */
class QwizardArchiveCreateForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The qwarchive manager.
   */
  protected QwArchiveManager $qwArchiveManager;

  /**
   * The record manager for qwarchive.
   */
  protected QwArchiveRecordManager $qwArchiveRecordManager;

  /**
   * The qwarchive user manager.
   */
  protected QwArchiveUserManager $qwArchiveUserManager;

  /**
   * Constructs a new QwizardArchiveCreateForm form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\qwarchive\QwArchiveManager $qw_archive_manager
   *   The qwarchive manager.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $qw_archive_record_manager
   *   The record manager for qwarchive.
   * @param \Drupal\qwarchive\QwArchiveUserManager $qw_archive_user_manager
   *   The qwarchive user manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, QwArchiveManager $qw_archive_manager, QwArchiveRecordManager $qw_archive_record_manager, QwArchiveUserManager $qw_archive_user_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->qwArchiveManager = $qw_archive_manager;
    $this->qwArchiveRecordManager = $qw_archive_record_manager;
    $this->qwArchiveUserManager = $qw_archive_user_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('queue'),
      $container->get('qwarchive.manager'),
      $container->get('qwarchive.record_manager'),
      $container->get('qwarchive.user_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwarchive_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['accounts'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Select users'),
      '#target_type' => 'user',
      '#multiple' => TRUE,
      '#tags' => TRUE,
      '#required' => TRUE,
      '#description' => $this->t('Select the users to archive the data for. Use comma to separate multiple users.'),
    ];

    $data_options = [
      'student_result' => $this->t('Student Result'),
      'qwiz_result' => $this->t('Quiz Result'),
      'qwiz_pools' => $this->t('Quiz Pools'),
      'subscriptions' => $this->t('Subscriptions'),
    ];

    $form['data_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Data to archive'),
      '#options' => $data_options,
      '#default_value' => array_keys($data_options),
      '#description' => $this->t('Select the data types you want to archive.'),
      '#disabled' => TRUE,
    ];

    $form['cron_queue'] = [
      '#type' => 'checkbox',
      '#title' => 'Process using cron queue',
      '#default_value' => TRUE,
    ];

    $form['info'] = [
      '#markup' => $this->t("<strong>Caution:</strong> The user data will be removed from the database & will be maintained in the files. This means, the data won't be accessible unless restored."),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $account_ids = array_column($values['accounts'], 'target_id');
    $existing_uids = [];
    // First make sure that record does not exists. We don't want multiple
    // records for single user.
    foreach ($account_ids as $uid) {
      $conditions = [
        ['field' => 'uid', 'value' => $uid],
      ];
      $existing_record = $this->qwArchiveRecordManager->getRecords($conditions);
      if (!empty($existing_record)) {
        // The record exists.
        $existing_uids[] = $uid;
      }
    }

    if (!empty($existing_uids)) {
      $users = $this->entityTypeManager->getStorage('user')->loadMultiple($existing_uids);
      $usernames = [];
      foreach ($users as $user_account) {
        $usernames[] = $user_account->getDisplayName();
      }
      $form_state->setErrorByName('accounts', $this->t('The data has been already archived for user(s) %names.', [
        '%names' => implode(', ', $usernames),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $account_ids = array_column($values['accounts'], 'target_id');
    // Filter users with data.
    $account_ids = $this->qwArchiveUserManager->getUsersWithData($account_ids);

    $data_types = array_filter($values['data_types']);
    $cron_process = (bool) $values['cron_queue'];
    if ($cron_process) {
      // Create a cron queue.
      $queue_name = 'qwarchive_process';
      $queue = $this->queueFactory->get($queue_name);
      $queue->createQueue();
      foreach ($account_ids as $uid) {
        $data = [
          'uid' => $uid,
          'data_types' => $data_types,
        ];
        $queue->createItem($data);
        // Add entry so we can see the status.
        $this->qwArchiveRecordManager->add($uid, $data_types, 'cron');
      }
    }
    else {
      $operations = [];
      foreach ($account_ids as $uid) {
        $operations[] = [
          QwArchiveBatch::class . '::archiveUserData', [$uid, $data_types],
        ];
        // Add entry so we can see the status.
        $this->qwArchiveRecordManager->add($uid, $data_types);
      }

      $batch = [
        'title' => $this->t('Archiving user data...'),
        'operations' => $operations,
        'finished' => QwArchiveBatch::class . '::archiveUserDataFinished',
        'init_message' => $this->t('Preparing to archive the user data...'),
        'progress_message' => $this->t('Processed @current out of @total...'),
        'batch_redirect' => Url::fromRoute('qwarchive.qw_archive_list'),
      ];
      batch_set($batch);
    }
    $form_state->setRedirect('qwarchive.qw_archive_list');
  }

}
