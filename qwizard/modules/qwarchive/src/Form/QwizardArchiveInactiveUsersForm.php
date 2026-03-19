<?php

namespace Drupal\qwarchive\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
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
class QwizardArchiveInactiveUsersForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The qwarchive manager.
   */
  protected QwArchiveManager $qwArchiveManager;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $qwArchiveRecordManager;

  /**
   * The qwarchive user manager.
   */
  protected QwArchiveUserManager $qwArchiveUserManager;

  /**
   * Constructs a QwizardArchiveInactiveUsersForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\qwarchive\QwArchiveManager $qw_archive_manager
   *   The qwarchive manager.
   * @param \Drupal\qwarchive\QwArchiveRecordManager $qw_archive_record_manager
   *   The qwarchive record manager.
   * @param \Drupal\qwarchive\QwArchiveUserManager $qw_archive_user_manager
   *   The qwarchive user manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueueFactory $queue_factory, DateFormatterInterface $date_formatter, QwArchiveManager $qw_archive_manager, QwArchiveRecordManager $qw_archive_record_manager, QwArchiveUserManager $qw_archive_user_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queueFactory = $queue_factory;
    $this->dateFormatter = $date_formatter;
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
      $container->get('date.formatter'),
      $container->get('qwarchive.manager'),
      $container->get('qwarchive.record_manager'),
      $container->get('qwarchive.user_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwarchive_inactive_users_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $config = $this->config('qwarchive.settings');
    $inactive_threshold = $config->get('inactive_threshold') ?: '12/31/2023';

    $query_params = $this->getRequest()->query->all();

    $account_status = NULL;
    if (isset($query_params['status']) && $query_params['status'] != '') {
      $account_status = $query_params['status'];
    }

    $filtered_accounts = [];
    $filtered_uids = [];
    if (!empty($query_params['uids'])) {
      $filtered_uids = $query_params['uids'];
      // Load the users.
      $filtered_accounts = $this->entityTypeManager->getStorage('user')->loadMultiple($filtered_uids);
    }

    $filtered_threshold = NULL;
    if (!empty($query_params['threshold'])) {
      $filtered_threshold = $query_params['threshold'];
      $inactive_threshold = $filtered_threshold;
    }

    $items_per_page = $query_params['items_per_page'] ?? 50;

    $weight = 0;

    // Add filters to the form.
    $form['filters'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Filters'),
      '#attributes' => [
        'class' => ['form--inline'],
      ],
      '#weight' => $weight++,
    ];
    $form['filters']['users'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Username(s)'),
      '#target_type' => 'user',
      '#multiple' => TRUE,
      '#tags' => TRUE,
      '#default_value' => $filtered_accounts,
    ];
    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        1 => $this->t('Active'),
        0 => $this->t('Blocked'),
      ],
      '#empty_option' => $this->t('- Any -'),
      '#default_value' => $account_status,
    ];
    $form['filters']['inactive_threshold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Inactive Threshold'),
      '#description' => $this->t('Time threshold (e.g. "2 years ago", "12/31/2023").'),
      '#default_value' => $inactive_threshold,
    ];

    $form['filters']['filter_actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['filter_actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#submit' => ['::filterForm'],
    ];

    $form['filters']['filter_actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    $form['cron_queue'] = [
      '#type' => 'checkbox',
      '#title' => 'Process using cron queue',
      '#default_value' => TRUE,
      '#weight' => $weight++,
    ];

    $form['info'] = [
      '#markup' => $this->t("<strong>Caution:</strong> The user data will be removed from the database & will be maintained in the files. This means, the data won't be accessible unless restored."),
      '#weight' => $weight++,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => $weight++,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Archive users'),
      '#button_type' => 'primary',
    ];

    $form['info_wrapper'] = [
      '#type' => 'container',
      '#weight' => $weight++,
    ];

    $form['info_wrapper']['data_info'] = [
      '#type' => 'html_tag',
      '#tag' => 'em',
      '#value' => $this->t("If there are no records for user, the data will not be archived."),
      '#attributes' => [
        'class' => ['user-data-info'],
      ],
    ];

    $form['info_wrapper']['items_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['form--inline', 'user-items-form-wrapper'],
      ],
    ];

    $form['info_wrapper']['items_wrapper']['items_per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Items per page'),
      '#default_value' => $items_per_page,
      '#size' => 4,
    ];

    $form['info_wrapper']['items_wrapper']['show'] = [
      '#type' => 'submit',
      '#value' => $this->t('Show'),
      '#submit' => ['::showForm'],
    ];

    $inactive_users = $this->qwArchiveUserManager->getInactiveUsers($inactive_threshold, $filtered_uids, $account_status, $items_per_page);

    $header = [
      'username' => $this->t('Username'),
      'student_results' => $this->t('Student Results'),
      'qwiz_results' => $this->t('Qwiz Results'),
      'qwiz_pools' => $this->t('Qwiz Pools'),
      'subscriptions' => $this->t('Subscriptions'),
      'status' => $this->t('Status'),
      'last_login' => $this->t('Last login'),
    ];

    $uids = array_keys($inactive_users);
    $bulk_counts = $this->qwArchiveUserManager->getBulkDataCounts($uids);

    $accounts = [];
    foreach ($inactive_users as $account) {
      /** @var \Drupal\user\UserInterface $account */
      $last_login = $account->getLastLoginTime();
      $uid = $account->id();
      // Use pre-fetched counts.
      $counts = $bulk_counts[$uid] ?? [
        'student_results' => 0,
        'qwiz_results' => 0,
        'qwiz_pools' => 0,
        'subscriptions' => 0,
      ];

      $accounts[$uid] = [
        'username' => [
          'data' => [
            '#type' => 'link',
            '#title' => $account->getDisplayName(),
            '#url' => $account->toUrl('edit-form'),
          ],
        ],
        'student_results' => $counts['student_results'],
        'qwiz_results' => $counts['qwiz_results'],
        'qwiz_pools' => $counts['qwiz_pools'],
        'subscriptions' => $counts['subscriptions'],
        'status' => $account->isActive() ? $this->t('Active') : $this->t('Blocked'),
        'last_login' => !empty($last_login) ? $this->dateFormatter->format($last_login, 'short') : $this->t('Never'),
      ];
    }

    $form['accounts'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $accounts,
      '#empty' => $this->t('No inactive users found for threshold: @threshold.', ['@threshold' => $inactive_threshold]),
      '#weight' => $weight++,
    ];

    $form['pager'] = [
      '#type' => 'pager',
      '#weight' => $weight++,
    ];

    $form['#attached']['library'] = ['qwarchive/user-list'];

    return $form;
  }

  /**
   * Filters the form by setting query parameters.
   */
  public function filterForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $params = $this->getRequest()->query->all();
    if (!empty($values['users'])) {
      $params['uids'] = array_column($values['users'], 'target_id');
    }
    if (!is_null($values['status']) || $values['status'] != '') {
      $params['status'] = $values['status'];
    }
    if (!empty($values['inactive_threshold'])) {
      $params['threshold'] = $values['inactive_threshold'];
    }
    if (empty($params)) {
      // No filters added. Return with warning.
      $this->messenger()->addWarning($this->t('No filters applied.'));
      return;
    }
    $form_state->setRedirect('qwarchive.qw_user_list', [], [
      'query' => $params,
    ]);
  }

  /**
   * Shows items per page.
   */
  public function showForm(array &$form, FormStateInterface $form_state) {
    $items_per_page = $form_state->getValue('items_per_page');
    $query_params = $this->getRequest()->query->all();
    if (!empty($items_per_page)) {
      $query_params['items_per_page'] = $items_per_page;
    }
    $form_state->setRedirect('qwarchive.qw_user_list', [], [
      'query' => $query_params,
    ]);
  }

  /**
   * Resets the fitler form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    // Reload the form.
    $form_state->setRedirect('qwarchive.qw_user_list');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $account_ids = array_filter($values['accounts']);
    // Filter users with data.
    $account_ids = $this->qwArchiveUserManager->getUsersWithData($account_ids);

    $cron_process = (bool) $values['cron_queue'];
    $data_types = [
      'student_result', 'qwiz_result', 'qwiz_pools', 'subscriptions',
    ];

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
