<?php

namespace Drupal\qwreporting\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\qwreporting\GroupImportUsersBatch;
use Drupal\qwreporting\GroupsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Form builder to import user to the group.
 */
class GroupImportUsersForm extends FormBase {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file system.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The group manager.
   */
  protected GroupsInterface $groupManager;

  /**
   * The uploaded file id.
   */
  protected ?int $fileId = NULL;

  /**
   * An array containing CSV data.
   */
  protected array $csvData = [];

  /**
   * Contructs GroupImportUsersForm form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\qwreporting\GroupsInterface $group_manager
   *   The group manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, GroupsInterface $group_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->groupManager = $group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('qwreporting.groups')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwreporting_group_import_users_form';
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

    $form['#title'] = $this->t('Import Students to @label', [
      '@label' => $group->label(),
    ]);

    $form['group_id'] = [
      '#type' => 'hidden',
      '#value' => $group_id,
    ];

    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Upload CSV file'),
      '#description' => $this->t('Upload a CSV file containing users. The file will not be saved.'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $validators = ['file_validate_extensions' => ['csv']];
    // Upload the file.
    $file = file_save_upload('csv_file', $validators, FALSE, 0, 1);

    if (empty($file)) {
      // Somethings wrong. Drupal will show the messages. Let's display the
      // generic error.
      $form_state->setErrorByName('csv_file', $this->t('Something is wrong with uploaded file. Please check the error messages or contact the administrator.'));
    }
    else {
      $this->fileId = $file->id();
      // We have our file. Parse it & make sure we have expected format.
      $real_path = $file->getFileUri();
      $real_path = $this->fileSystem->realpath($real_path);

      $csv_data = $this->parseCsv($real_path);

      // Make sure the data is not empty.
      if (empty($csv_data)) {
        $form_state->setErrorByName('csv_file', $this->t('No data found in uploaded CSV file.'));
        return;
      }

      // Get the headers from first element.
      if (!empty($csv_data[0])) {
        $headers = array_keys($csv_data[0]);
        // Make sure we have uid, email & username.
        if (!in_array('uid', $headers) && !in_array('email', $headers) && !in_array('username', $headers)) {
          $form_state->setErrorByName('csv_file', $this->t('Invalid CSV data. Either, uid, email or username is required. Please check the CSV data.'));
        }
      }
      $this->csvData = $csv_data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $group_id = $values['group_id'];
    $group = $this->groupManager->getGroup($group_id);

    if (!empty($this->fileId)) {
      $file = $this->entityTypeManager->getStorage('file')->load($this->fileId);

      // Delete temp file.
      $this->fileSystem->delete($file->getFileUri());

      $users_to_add = [];
      // Get existing students.
      $group_student_ids = array_column($group->get('field_students')->getValue(), 'target_id');

      if (!empty($this->csvData)) {
        $operations = [];
        foreach ($this->csvData as $record) {
          $uid = NULL;
          if (!empty($record['uid'])) {
            $uid = $record['uid'];
          }
          elseif (!empty($record['email'])) {
            $account = user_load_by_mail($record['email']);
            if ($account) {
              $uid = $account->id();
            }
            else {
              \Drupal::logger('group_user_import')->warning("User not found for <br>@var", [
                '@var' => $record['email'],
              ]);
              continue;
            }
          }
          elseif (!empty($record['username'])) {
            $account = user_load_by_name($record['username']);
            if ($account) {
              $uid = $account->id();
            }
            else {
              \Drupal::logger('group_user_import')->warning("User not found for <br>@var", [
                '@var' => $record['username'],
              ]);
              continue;
            }
          }
          // Make sure to add unique uids. We don't want same user to be added.
          // Also we don't want to add user that already belongs to the group.
          if (empty($uid) || (!empty($group_student_ids) && in_array($uid, $group_student_ids))) {
            // Skip this user.
            continue;
          }
          $users_to_add[$uid] = $record;
        }

        foreach ($users_to_add as $record) {
          $operations[] = [
            GroupImportUsersBatch::class . '::importUser',
            [$record, $group_id],
          ];
        }

        $batch = [
          'title' => $this->t('Importing users to @label...', [
            '@label' => $group->label(),
          ]),
          'operations' => $operations,
          'finished' => GroupImportUsersBatch::class . '::importUsersFinished',
          'batch_redirect' => Url::fromRoute('qwreporting.group_edit', [
            'group', $group_id,
          ]),
        ];
        batch_set($batch);
      }
      else {
        $this->messenger()->addError($this->t('Could not process the uploaded file or no record found.'));
      }
    }
    $form_state->setRedirect('qwreporting.group_edit', ['group' => $group_id]);
  }

  /**
   * Coverts csv into array.
   */
  private function parseCsv($path) {
    $rows = [];
    if (($handle = fopen($path, 'r')) !== FALSE) {
      $header = fgetcsv($handle);
      while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($header) != count($data)) {
          continue;
        }
        $row = array_combine($header, $data);
        if ($row !== FALSE) {
          $rows[] = $row;
        }
      }
      fclose($handle);
    }
    return $rows;
  }

}
