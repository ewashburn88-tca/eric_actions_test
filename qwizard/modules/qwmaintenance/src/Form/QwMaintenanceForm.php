<?php

namespace Drupal\qwmaintenance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\qwmaintenance\Controller\QWMaintenancePoolsOneUser;
use Drupal\qwmaintenance\PoolsMaintenanceService;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Render\Markup;

/**
 * Class QwMaintenanceForm.
 */
class QwMaintenanceForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qw_maintenance_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    // @todo D10
    //   $user_admin = \Drupal::service('permission_checker')->hasPermission('administer users', $current_user);
    $user_admin = $current_user->hasPermission('administer users');
    // Restrict form to user admins.
    if (!$user_admin) {
      $url = Url::fromRoute('system.403');
      return new RedirectResponse($url->toString());
    }

    if (!empty(\Drupal::request()->query->get('group_id'))) {
      $group_id = \Drupal::request()->query->get('group_id');
      $form['select_groups'] = [
        '#type'  => 'value',
        '#value' => [$group_id],
      ];
      $group = Term::load($group_id);
      $markup = '<h1>' . $this->t('User results maintenance for <span style="color: red">') . $group->name->value . '.</span></h1>';
      $markup .= '<h2>' . $this->t('This will perform the task on student users in the group.') . '</h2>';
      $form['group_info'] = [
        '#type'   => 'markup',
        '#markup' => Markup::create($markup),
        '#weight' => -100,
      ];
    }
    elseif (!empty(\Drupal::request()->query->get('user_id'))) {
      $user_id = \Drupal::request()->query->get('user_id');
      $form['select_user'] = [
        '#type'  => 'value',
        '#value' => [$user_id],
      ];
      $acct = User::load($user_id);
      $markup = '<h1>' . $this->t('User results maintenance for ') . '<span style="color: red">' . $acct->name->value . '</span>.</h1>';
      $markup .= '<h2>' . $this->t('This will perform the task on the student.') . '</h2>';
      $form['user_info'] = [
        '#type'   => 'markup',
        '#markup' => Markup::create($markup),
        '#weight' => -100,
      ];
    }
    else {
      $form['select_user'] = [
        '#type'          => 'entity_autocomplete',
        '#title'         => $this->t('Usernames'),
        '#description'   => $this->t('Enter a comma separated list of user names.'),
        '#target_type'   => 'user',
        '#tags'          => TRUE,
        '#default_value' => '',
      ];

      /*
      $form['select_user'] = [
        '#type'                            => 'autocomplete_deluxe',
        '#title'                           => $this->t('Select User'),
        '#autocomplete_deluxe_path'        => new Url(
          'autocomplete_deluxe/user/name',
          ['absolute' => TRUE]
        ),
        '#multiple'                        => TRUE,
        '#autocomplete_min_length'         => 0,
        '#autocomplete_multiple_delimiter' => ',',
        '#not_found_message'               => "The term '@term' will be added.",
        '#weight'                          => '0',
      ];
      */

      $form['ignore_sub_100'] = [
        '#type'          => 'checkbox',
        '#title'         => 'Ignore uids < 100',
        '#default_value' => TRUE,
        '#description'   => $this->t('Skip admin accounts, uid < 100'),
        '#states'        => [
          // Only show this field users aren't specified.
          'invisible' => [
            ':input[name="select_user"]' => ['filled' => TRUE],
          ],
        ],
      ];

      $course_roles = ['bcse' => 'BCSE', 'navle' => 'NAVLE', 'vtne' => 'VTNE'];
      $form['users_with_role'] = [
        '#type'          => 'checkboxes',
        '#title'         => 'Only select users with course role:',
        '#default_value' => array_keys($course_roles),
        '#options'       => $course_roles,
        '#description'   => $this->t('Users without selected course roles will be ignored'),
        '#states'        => [
          // Only show this field users aren't specified.
          'invisible' => [
            ':input[name="select_user"]' => ['filled' => TRUE],
          ],
        ],
      ];

      $form['dates'] = [
        '#type'        => 'details',
        '#title'       => t('Dates'),
        '#description' => $this->t('If checked, all other options above will be ignored.'),
        '#open'        => FALSE,
        '#states'        => [
          // Only show this field users aren't specified.
          'invisible' => [
            ':input[name="select_user"]' => ['filled' => TRUE],
          ],
        ],
      ];
      $form['dates']['last_access'] = [
        '#type'              => 'datetime',
        '#date_date_element' => 'date',
        '#date_time_element' => 'none',
        '#title'             => $this->t('Last accessed since'),
        '#description'       => $this->t('Only update users that logged in after this date.'),
        '#default_value'     => NULL,
        '#size'              => 20,
        '#states'        => [
          // Only show this field users aren't specified.
          'invisible' => [
            ':input[name="select_user"]' => ['filled' => TRUE],
          ],
        ],
      ];
      $form['dates']['created_before'] = [
        '#type'              => 'datetime',
        '#date_date_element' => 'date',
        '#date_time_element' => 'none',
        '#title'             => $this->t('Created Before'),
        '#description'       => $this->t('Only update users that were created before this date.'),
        '#default_value'     => NULL,
        '#size'              => 20,
        '#states'        => [
          // Only show this field users aren't specified.
          'invisible' => [
            ':input[name="select_user"]' => ['filled' => TRUE],
          ],
        ],
      ];

      // Group selection.
      $groups_service = \Drupal::service('qwreporting.groups');
      $groups = $groups_service->getGroups();
      $groups = Term::loadMultiple(array_keys($groups));
      $groups_to_rebuild = [
        'all' => 'All Groups',
      ];
      foreach ($groups as $group) {
        $groups_to_rebuild[$group->id()] = $group->name->value;
      }
      $form['select_groups_wrap'] = [
        '#type'         => 'details',
        '#title'        => t('Reporting Groups'),
        '#description'  => $this->t('If checked, all other options above will be ignored.'),
        '#open'         => FALSE,
        'select_groups' => [
          '#type'          => 'checkboxes',
          '#title'         => $this->t('Select maintenance items'),
          '#options'       => $groups_to_rebuild,
          '#default_value' => ['Rebuild Question Pools & Results'],
          '#weight'        => '10',
        ],
        '#states'        => [
          // Only show this field users aren't specified.
          'invisible' => [
            ':input[name="select_user"]' => ['filled' => TRUE],
          ],
        ],
      ];
    }

    $form['skip_queue'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Skip Queue'),
      '#description'   => $this->t('If checked, import will be processed immediately. Don\'t use for large groups.'),
      '#default_value' => FALSE,
    ];

    $form['select_maintenance_items'] = [
      '#type'          => 'checkboxes',
      '#title'         => $this->t('Select maintenance items'),
      '#options'       => [
        //'Rebuild Student Results' => $this->t('Rebuild Student Results'),
        'Rebuild Results'                                   => $this->t('Rebuild Results'),
        'Rebuild Question Pools & Results'                  => $this->t('Rebuild Question Pools & Results'),
        'Delete Pools and Rebuild Question Pools & Results' => $this->t('Delete Pools and Rebuild Question Pools & Results'),
        'Rebuild secondary Question Pools & Results'        => $this->t('Rebuild Secondary Question Pools & Results (not study/test mode)'),
        'Resave Snapshots'                                  => $this->t('Resave Snapshots'),
        //'Archive User' => $this->t('Archive User')
      ],
      '#default_value' => ['Rebuild Question Pools & Results'],
      '#weight'        => '10',
    ];

    $form['perform_maintenance'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Perform Maintenance'),
      '#weight' => '20',
    ];

    // Help text
    $form['help'] = [
      '#type'       => 'fieldset',
      '#title'      => 'Help',
      '#weight'     => 100,
      '#attributes' => [
        'class' => ['zuku-admin-form-fieldset'],
      ],
    ];
    $form['help']['queue_ui'] = [
      '#type'   => 'markup',
      '#markup' => "<p><a target='_blank' href='/admin/config/system/queue-ui'>Queue UI. Use to reset queue or run all</a></p>",
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $uids = [];
    $qwMaintenancePools = new QWMaintenancePoolsOneUser;
    set_time_limit(300);

    // Group selection takes precedence over other user selections.
    if (!empty($values['select_groups'])) {
      $select_groups = array_filter($values['select_groups']);
      $groups_service = \Drupal::service('qwreporting.groups');
      if ($select_groups == 'all') {
        $groups = $groups_service->getGroups();
        $groups = Term::loadMultiple(array_keys($groups));
      }
      else {
        $groups = Term::loadMultiple(array_filter($select_groups));
      }
      foreach ($groups as $group) {
        $group_users = array_column($group->field_students->getValue(), 'target_id');
        foreach ($group_users as $uid) {
          $uids[$uid] = $uid;
        }
      }
    }
    elseif (!empty($values['select_user'])) {
      if (!is_array($values['select_user'])) {
        $uids = [$values['select_user']];
      }
      elseif (empty($uids)) {
        foreach ($values['select_user'] as $user_value) {
          $uids[$user_value] = intval($user_value);
        }
      }
    }
    else {
      $query = \Drupal::entityQuery('user');
      if (!empty($created_before)) {
        $created_before = strtotime($values['created_before']);
        $query->condition('created', $created_before, '<=');
      }
      if (!empty($last_access)) {
        $last_access = strtotime($values['last_access']);
        $query->condition('access', $last_access, '>=');
      }
      if (!empty($values['ignore_sub_100'])) {
        $query->condition('uid', 100, '>=');
      }
      if (!empty($values['users_with_role'])) {
        $selected_roles = $values['users_with_role'];
        foreach ($selected_roles as $key => $value) {
          if (empty($value)) {
            unset($selected_roles[$key]);
          }
        }
        $selected_roles = array_keys($selected_roles);

        if (!empty($selected_roles)) {
          $query->condition('roles', $selected_roles, 'IN');
        }
      }
      $query->sort('access', 'DESC');
      $uids = $query->execute();
    }
    $values['select_maintenance_items'] = array_filter($values['select_maintenance_items']);
    if ($values['skip_queue']) {
      // Skip batch&queue entirely, just process it. This assumes 1 user being processed.
      $controller = new QWMaintenancePoolsOneUser;
      foreach ($uids as $uid) {
        if (!empty($values['select_maintenance_items']['Rebuild Question Pools & Results'])) {
          $controller->rebuildPools($uid, TRUE);
        }
        elseif (!empty($values['select_maintenance_items']['Delete Pools and Rebuild Question Pools & Results'])) {
          $controller->rebuildPools($uid, TRUE, FALSE, FALSE, TRUE);
        }
        elseif (!empty($values['select_maintenance_items']['Rebuild secondary Question Pools & Results'])) {
          $controller->rebuildPools($uid, TRUE, FALSE, TRUE);
        }
        elseif (!empty($values['select_maintenance_items']['Rebuild Results'])) {
          $qwMaintenancePools->rebuildPools($uid, FALSE, TRUE, FALSE, FALSE, FALSE);
        }

        if (!empty($values['select_maintenance_items']['Resave Snapshots'])) {
          $controller->resaveSnapshots($uid);
        }
      }
      \Drupal::messenger()
        ->addMessage(count($uids) . ' processed ' . implode(' | ', $values['select_maintenance_items']));
    }
    else {
      $ops = [];
      foreach ($uids as $uid) {
        $ops[] = [
          '\Drupal\qwmaintenance\Form\QwMaintenanceForm::processOps',
          [$uid, $values['select_maintenance_items']],
        ];
      }
      $batch = [
        'title'      => t('QW Maintenance'),
        'operations' => $ops,
        'finished'   => '\Drupal\qwmaintenance\Form::finishProcessing',
      ];
      batch_set($batch);
      \Drupal::messenger()
        ->addMessage(count($uids) . ' added to the queue with the operations ' . implode(' | ', $values['select_maintenance_items']));
    }
  }

  /**
   * Batch process function.
   */
  public static function processOps($uid, $values, &$context) {
    $message = 'Adding Users to cron queue...' . $uid;

    $queue = \Drupal::queue('qwmaintenance_queue');
    $queue->createQueue();
    $item = new \stdClass();
    $item->uid = $uid;
    $item->operations = $values;

    $result = $queue->createItem($item);
    // Add result to state.
    if ($result) {
      $context['results'][$uid] = $result;
    }
    else {
      $context['results'][$uid] = 'Failure';
    }

    $context['message'] = $message;
  }

  /**
   *
   */
  public static function finishProcessing($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    //@todo count is off, 0
    if ($success) {
      $message = t('@count users queued, users will be processed next cron run.', ['@count' => count($results)]);

      $failed = array_filter($results, function ($value) {
        return $value == 'Failed';
      });
      if (!empty($failed)) {
        $message .= ' Not saved: ' . implode(', ', array_keys($failed));
      }
    }
    else {
      $message = t('Finished with an error.');
    }
    \Drupal::messenger()->addMessage($message);
  }

}
