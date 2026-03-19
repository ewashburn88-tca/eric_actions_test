<?php

namespace Drupal\qwreporting\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qwreporting\GroupBulkOpsBatch;
use Drupal\qwreporting\GroupsInterface;
use Drupal\qwreporting\QwreportingGroupOps;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form builder for group user operations.
 */
class GroupOpsForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The group manager.
   */
  protected GroupsInterface $groupManager;

  /**
   * The group operations manager.
   */
  protected QwreportingGroupOps $groupOpsManager;

  /**
   * Default value for account status.
   */
  protected string $defaultAccoutStatus;

  /**
   * Contructs GroupOpsForm form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\qwreporting\GroupsInterface $group_manager
   *   The group manager.
   * @param \Drupal\qwreporting\QwreportingGroupOps $group_ops_manager
   *   The group operations manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, GroupsInterface $group_manager, QwreportingGroupOps $group_ops_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->groupManager = $group_manager;
    $this->groupOpsManager = $group_ops_manager;
    $this->defaultAccoutStatus = 'NA';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('qwreporting.groups'),
      $container->get('qwreporting.group_ops')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwreporting_group_Ops';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group_id = NULL) {
    $form = [];

    $group = $this->groupManager->getGroup($group_id);
    if (empty($group)) {
      throw new NotFoundHttpException();
    }

    $form['#title'] = $this->t('Batch Operations for @label Students', [
      '@label' => $group->label(),
    ]);

    $form['group_id'] = [
      "#type" => 'hidden',
      '#value' => $group_id,
    ];

    // Let's build the form in 2 columns for better ux.
    $form['row'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['row'],
      ],
    ];
    $form['row']['column_1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-4'],
      ],
    ];
    $form['row']['column_2'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-8'],
      ],
    ];

    $form['row']['column_1']['account']['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Account Status'),
      '#options' => [
        $this->defaultAccoutStatus => $this->t('No Change'),
        0 => $this->t('Disable'),
        1 => $this->t('Enable'),
      ],
      '#default_value' => $this->defaultAccoutStatus,
      '#description' => $this->t('Choose disable to block accounts.'),
      '#required' => FALSE,
    ];
    $form['row']['column_1']['account']['logout'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Logout Users'),
      '#default_value' => FALSE,
      '#required' => FALSE,
    ];

    $form['row']['column_1']['subscription']['end_subscription'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('End Subscriptions'),
      '#default_value' => FALSE,
      '#required' => FALSE,
    ];

    $form['row']['column_1']['subscription']['renew'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Subscription Renewals'),
      '#collapsible' => TRUE,
      '#collapsed'  => FALSE,
    ];
    $form['row']['column_1']['subscription']['renew']['days'] = [
      '#type' => 'number',
      '#title' => $this->t('Days to extend.'),
    ];
    $form['row']['column_1']['subscription']['renew']['info_or'] = [
      '#markup' => '<strong>' . $this->t('Or') . '</strong>',
    ];
    $form['row']['column_1']['subscription']['renew']['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date for expiration.'),
    ];
    $form['row']['column_1']['subscription']['premium'] = [
      '#type' => 'radios',
      '#title' => $this->t('Premium Status'),
      '#options' => [
        $this->defaultAccoutStatus => $this->t('No Change'),
        'disable' => $this->t('Remove Premium'),
        'enable' => $this->t('Add Premium'),
      ],
      '#default_value' => $this->defaultAccoutStatus,
      '#required' => FALSE,
    ];

    $form['row']['column_1']['subscription']['special'] = [
      '#type' => 'radios',
      '#title' => $this->t('Special Product Status'),
      '#options' => [
        $this->defaultAccoutStatus => $this->t('No Change'),
        'disable' => $this->t('Remove Special product'),
        'enable' => $this->t('Add Special product'),
      ],
      '#default_value' => $this->defaultAccoutStatus,
      '#required' => FALSE,
    ];

    $form['row']['column_1']['skip_queue'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip Queue'),
      '#description' => $this->t("If checked, import will be processed immediately. Don't use for large groups."),
      '#default_value' => FALSE,
    ];

    // Header for table.
    $header = [
      'name' => $this->t('Name'),
      'email' => $this->t('Email'),
      'last_access' => $this->t('Last access'),
    ];

    // Get students.
    $students = $this->groupManager->getGroupStudents($group);
    $student_options = $this->prepareUserOptions($students);
    $form['row']['column_2']['students'] = [
      '#type' => 'tableselect',
      '#title' => $this->t('Students'),
      '#header' => $header,
      '#options' => $student_options,
      '#empty' => $this->t('No student is available.'),
      '#attributes' => [
        'class' => ['small', 'group-ops-table-options'],
      ],
      '#prefix' => '<label>' . $this->t('Students') . '</label>',
    ];

    // Administrators.
    $admins = $this->groupManager->getGroupAdmins($group);
    $admin_options = $this->prepareUserOptions($admins);
    $form['row']['column_2']['admins'] = [
      '#type' => 'tableselect',
      '#title' => $this->t('Administrators'),
      '#header' => $header,
      '#options' => $admin_options,
      '#empty' => $this->t('No administrator is available.'),
      '#attributes' => [
        'class' => ['small', 'group-ops-table-options'],
      ],
      '#prefix' => '<label>' . $this->t('Administrators') . '</label>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Proceed'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (empty(array_filter($values['students'])) && empty(array_filter($values['admins']))) {
      $form_state->setErrorByName('students', $this->t('You must select at least one student.'));
      $form_state->setErrorByName('admins', $this->t('You must select at least one administrator'));
    }

    if (!empty($values['end_subscription'])) {
      // Subscriptions will be ended. Make sure that days or date to extend the
      // subscription are not filled in.
      if (!empty($values['days'])) {
        $form_state->setErrorByName('days', $this->t('Do not provide the days to extend the subscription when End Subscriptions is checked.'));
      }
      if (!empty($values['date'])) {
        $form_state->setErrorByName('date', $this->t('Do not provide the date to extend the subscription when End Subscriptions is checked.'));
      }
    }

    // Make sure that both days & date to extend subscriptions are not provided.
    if (!empty($values['days']) && !empty($values['date'])) {
      $form_state->setError($form['row']['column_1']['subscription']['renew'], $this->t('Either days to extend or date for expiration should be provided for subscriptions.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!empty($values['students'])) {
      $student_ids = array_filter($values['students']);
    }
    if (!empty($values['admins'])) {
      $admin_ids = array_filter($values['admins']);
    }

    $group_id = $values['group_id'];
    $group = $this->groupManager->getGroup($group_id);
    // Get course id, we need it later.
    $course_id = (int) $group->field_course->target_id;

    $skip_queue = (bool) $values['skip_queue'];

    $user_ids = array_values(array_merge($student_ids, $admin_ids));
    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage->loadMultiple($user_ids);

    $batch_has_ops = FALSE;

    $batch_builder = (new BatchBuilder())
      ->setTitle('Performing group operations...')
      ->setFinishCallback(GroupBulkOpsBatch::class . '::groupOpsBatchFinished');

    foreach ($users as $account) {

      if ($values['status'] != $this->defaultAccoutStatus) {
        $user_status = $values['status'] == 'enable';
        if ($skip_queue) {
          $this->groupOpsManager->changeUserAccountStatus($account, $user_status);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::changeAccountStatus', [
            $account,
            $user_status,
          ]);
          $batch_has_ops = TRUE;
        }
      }

      $logout = (bool) $values['logout'];
      if ($logout && $account->id() != $this->currentUser()->id()) {
        // We cannot logout currently logged in user.
        if ($skip_queue) {
          // Log out the user.
          $this->groupOpsManager->logoutUser($account);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::logoutUser', [$account]);
          $batch_has_ops = TRUE;
        }
      }

      // Only one of these will apply. We don't have to check the values here
      // since validateForm makes sure we get one value only.
      $end_subscription = (bool) $values['end_subscription'];
      if ($end_subscription) {
        if ($skip_queue) {
          $this->groupOpsManager->endSubscription($account, $course_id);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::endSubscription', [$account, $course_id]);
          $batch_has_ops = TRUE;
        }
      }

      $date = $values['date'];
      if (!empty($date)) {
        $type = 'date';
        if ($skip_queue) {
          $this->groupOpsManager->extendSubscription($account, $date, $course_id, $type);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::extendSubscription', [
            $account,
            $date,
            $course_id,
            $type,
          ]);
          $batch_has_ops = TRUE;
        }
      }

      $days = $values['days'];
      if (!empty($days)) {
        $type = 'days';
        if ($skip_queue) {
          $this->groupOpsManager->extendSubscription($account, $days, $course_id, $type);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::extendSubscription', [
            $account,
            $days,
            $course_id,
            $type,
          ]);
          $batch_has_ops = TRUE;
        }
      }

      if ($values['premium'] != $this->defaultAccoutStatus) {
        // Status is selected. Add batch operations for selected users.
        $premium_status = $values['premium'] == 'enable';
        if ($skip_queue) {
          $this->groupOpsManager->extendSubscription($account, $premium_status, $course_id);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::changeAccountPremium', [
            $account,
            $premium_status,
            $course_id,
          ]);
          $batch_has_ops = TRUE;
        }
      }
      if ($values['special'] != $this->defaultAccoutStatus) {
        // Status is selected. Add batch operations for selected users.
        $special_status = $values['special'] == 'enable';
        if ($skip_queue) {
          $this->groupOpsManager->changeAccountSpecial($account, $special_status);
        }
        else {
          $batch_builder->addOperation(GroupBulkOpsBatch::class . '::changeAccountSpecial', [
            $account,
            $special_status,
          ]);
          $batch_has_ops = TRUE;
        }
      }
    }

    if ($skip_queue) {
      // Show the message.
      $this->messenger()->addStatus($this->t('Group operations executed successfully.'));
    }

    if ($batch_has_ops) {
      batch_set($batch_builder->toArray());
    }
  }

  /**
   * Prepare user options.
   */
  protected function prepareUserOptions($accounts = []) {
    $options = [];
    foreach ($accounts as $account) {
      $last_accessed = $account['last_access'];
      $formatted_time = $this->t('never');
      if (!empty($last_accessed)) {
        $formatted_time = $this->t('@interval ago', [
          '@interval' => $this->dateFormatter->formatTimeDiffSince($last_accessed),
        ]);
      }
      // Decide what all needs to be displayed.
      $options[$account['id']] = [
        'name' => $account['formatted_name'],
        'email' => $account['email'],
        'last_access' => $formatted_time,
      ];
    }
    return $options;
  }

}
