<?php

namespace Drupal\qwreporting\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\qwreporting\GroupsInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form builder for group emails.
 */
class GroupEmailForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The queue factory.
   */
  protected QueueFactory $queueFactory;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The group manager.
   */
  protected GroupsInterface $groupManager;

  /**
   * Contructs GroupEmailForm form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\qwreporting\GroupsInterface $group_manager
   *   The group manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, QueueFactory $queue_factory, DateFormatterInterface $date_formatter, GroupsInterface $group_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->queueFactory = $queue_factory;
    $this->dateFormatter = $date_formatter;
    $this->groupManager = $group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('queue'),
      $container->get('date.formatter'),
      $container->get('qwreporting.groups')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwreporting_group_email';
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

    $form['#title'] = $this->t('Email @label Students', [
      '@label' => $group->label(),
    ]);

    $form['group_id'] = [
      '#type' => 'hidden',
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
        'class' => ['col-sm-12', 'col-md-6'],
      ],
    ];
    $form['row']['column_2'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['col-sm-12', 'col-md-6'],
      ],
    ];

    // Get available email templates.
    $templates = $this->entityTypeManager->getStorage('zuku_et')->loadMultiple();

    $template_options = [];
    // Only use template options if user has access to use those.
    $use_access = $this->entityTypeManager->getAccessControlHandler('zuku_et')->checkUseAccess($this->currentUser);
    if ($use_access->allowed()) {
      foreach ($templates as $template) {
        $template_options[$template->id()] = $template->getTitle();
      }
    }

    // Add custom option to allow editing of an email.
    $template_options['custom'] = $this->t('Custom');

    $default_template = $form_state->getValue('template');
    if (empty($default_template)) {
      $default_template = $this->config('zuku_et.settings')->get('default_group_et');
    }

    // Template selection.
    $form['row']['column_1']['template'] = [
      '#type' => 'select',
      '#title' => $this->t('Email template'),
      '#options' => $template_options,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $default_template,
      '#ajax' => [
        'callback' => '::processTemplateSelection',
        'wrapper' => 'group-email-container-wrapper',
      ],
    ];

    // Container for email fields.
    $form['row']['column_1']['email_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'group-email-container-wrapper'],
    ];

    if (!empty($default_template)) {
      $input = $form_state->getUserInput();
      $subject = NULL;
      $body = [];
      if ($default_template != 'custom') {
        // Custom template is not selected.
        // Get the email template.
        $email_template = $templates[$default_template];
        // Let's get subject & body from template.
        $subject = $email_template->getSubject();
        $body = $email_template->getBody();
      }
      // Setting up #default_value is not enough when it needs to be updated
      // via ajax. We need to set the input in form state as well.
      $input = $form_state->getUserInput();
      $input['subject'] = $subject;
      $input['body'] = $body;
      $form_state->setUserInput($input);

      $form['row']['column_1']['email_container']['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Subject'),
        '#default_value' => $subject,
        '#required' => TRUE,
      ];
      $form['row']['column_1']['email_container']['body'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Body'),
        '#default_value' => !empty($body['value']) ? $body['value'] : NULL,
        '#format' => !empty($body['format']) ? $body['format'] : filter_fallback_format(),
        '#required' => TRUE,
      ];

      $form['row']['column_1']['email_container']['tokens'] = [
        '#title' => $this->t('Replacement patterns'),
        '#type' => 'details',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        'token_tree' => [
          '#theme' => 'token_tree_link',
          '#token_types' => ['user'],
        ],
      ];

      // Add available custom token help.
      $custom_tokens = $this->groupManager->getCustomTokens();
      $rows = [];
      foreach ($custom_tokens as $token => $description) {
        $rows[] = [
          new FormattableMarkup('<code>@token</code>', [
            '@token' => $token,
          ]),
          $description,
        ];
      }
      $form['row']['column_1']['email_container']['custom_tokens'] = [
        '#theme' => 'table',
        '#header' => [
          $this->t('Token'), $this->t('Description'),
        ],
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['small', 'group-email-table-options'],
        ],
        '#prefix' => '<label>' . $this->t('Custom tokens') . '</label>',
      ];
    }

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
        'class' => ['small', 'group-email-table-options'],
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
        'class' => ['small', 'group-email-table-options'],
      ],
      '#prefix' => '<label>' . $this->t('Administrators') . '</label>',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Send Email'),
      ],
    ];

    // Add our library for tweaks.
    $form['#attached']['library'][] = 'qwreporting/qwreporting.group_email';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $group_id = $values['group_id'];

    $subject = $values['subject'];
    $body = $values['body'];
    // User storage.
    $user_storage = $this->entityTypeManager->getStorage('user');
    // Prepare an array of selected user accounts (students & admins both).
    $emails = [];
    // Token data for replacements.
    $token_data = [];
    // Other data for replacements.
    $replacements = [];
    $custom_tokens = $this->groupManager->getCustomTokens();
    $tokens_present = [];
    foreach ($custom_tokens as $token => $description) {
      if (str_contains($subject, $token) || str_contains($body['value'], $token)) {
        $tokens_present[] = $token;
      }
    }

    $group = $this->entityTypeManager->getStorage('taxonomy_term')->load($group_id);

    $emails = [];
    $token_data = [];
    $replacements = [];

    // Students.
    $students = $values['students'];
    foreach ($students as $student_uid) {
      if (!empty($student_uid)) {
        $account = $user_storage->load($student_uid);
        if (!empty($account) && $account instanceof UserInterface) {
          $email = $account->getEmail();
          $emails[$account->id()] = $email;
          $token_data[$account->id()]['user'] = $account;

          $custom_token_data = [
            'user' => $account,
            'group' => $group,
          ];
          $replacements[$account->id()] = $this->groupManager->getReplacedCustomTokens($tokens_present, $custom_token_data);
        }
      }
    }

    // Admins.
    $admins = $values['admins'];
    foreach ($admins as $admin_uid) {
      if (!empty($admin_uid)) {
        $account = $user_storage->load($admin_uid);
        if (!empty($account) && $account instanceof UserInterface) {
          $email = $account->getEmail();
          $emails[$account->id()] = $email;
          $token_data[$account->id()]['user'] = $account;

          $custom_token_data = [
            'user' => $account,
            'group' => $group,
          ];
          $replacements[$account->id()] = $this->groupManager->getReplacedCustomTokens($tokens_present, $custom_token_data);
        }
      }
    }

    $count = 0;
    if (!empty($emails)) {
      // Get the queue worker. It must be reliable since execution of each item
      // at least once is important.
      $queue = $this->queueFactory->get('zuku_et_email_queue', TRUE);
      $queue->createQueue();
      foreach ($emails as $uid => $email) {
        $item = [
          'to' => $email,
          'email' => [
            'subject' => $subject,
            'body' => $body,
          ],
          'data' => !empty($token_data[$uid]) ? $token_data[$uid] : [],
          'replacements' => !empty($replacements[$uid]) ? $replacements[$uid] : [],
        ];
        $queue->createItem($item);
        $count++;
      }
    }

    if ($count > 0) {
      $message = $this->formatPlural($count, 'Thank you! An email will be sent shortly.', 'Thank you! @count emails will be sent shortly.');
      $this->messenger()->addStatus($message);
    }
    else {
      $this->messenger()->addError($this->t('Sorry, something has gone wrong. No emails will be sent. Please contact the administrator.'));
    }
    $form_state->setRedirect('qwreporting.results.individual', ['group' => $group_id]);
  }

  /**
   * Ajax callback for template selection.
   */
  public function processTemplateSelection(array $form, FormStateInterface $form_state) {
    return $form['row']['column_1']['email_container'];
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
      // @todo add info if emails already sent.
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
