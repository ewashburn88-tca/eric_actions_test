<?php

namespace Drupal\qwmaintenance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qwmaintenance\QwDataCleanupManager;
use Drupal\qwmaintenance\QwMaintenanceCleanupBatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Orphaned data cleanup form.
 */
class QwOrphanedDataCleanupForm extends FormBase {

  /**
   * The data cleanup manager.
   *
   * @var \Drupal\qwmaintenance\QwDataCleanupManager
   */
  protected $dataCleanupManager;

  /**
   * Constructs a new QwizardArchiveCreateForm form.
   *
   * @param \Drupal\qwmaintenance\QwDataCleanupManager $data_cleanup_manager
   *   The data cleanup manager.
   */
  public function __construct(QwDataCleanupManager $data_cleanup_manager) {
    $this->dataCleanupManager = $data_cleanup_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qwmaintenance.data_cleanup')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qw_orphaned_data_cleanup_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $snapshot_count = $this->dataCleanupManager->getOrphanedSnapshots(TRUE);
    $result_count = $this->dataCleanupManager->getOrphanedResults(TRUE);

    $form['data'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Data to clean up'),
      '#options' => [
        'snapshots' => $this->t('Snapshots (Found @count)', ['@count' => $snapshot_count]),
        'results' => $this->t('Results (Found @count)', ['@count' => $result_count]),
      ],
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clean up'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = $form_state->getValue('data');

    $operations = [];
    if (!empty($data['snapshots'])) {
      $snapshot_count = $this->dataCleanupManager->getOrphanedSnapshots(TRUE);
      if ($snapshot_count > 0) {
        $operations[] = [
          QwMaintenanceCleanupBatch::class . '::processOrphanedSnapshots', [$snapshot_count],
        ];
      }
    }

    if (!empty($data['results'])) {
      $result_count = $this->dataCleanupManager->getOrphanedResults(TRUE);
      if ($result_count > 0) {
        $operations[] = [
          QwMaintenanceCleanupBatch::class . '::processOrphanedResults', [$result_count],
        ];
      }
    }

    if (!empty($operations)) {
      $batch = [
        'title' => $this->t('Deleting orphaned data...'),
        'operations' => $operations,
        'finished' => QwMaintenanceCleanupBatch::class . '::orphanedDataCleanupFinished',
        'init_message' => $this->t('Preparing to delete orphaned data...'),
        'progress_message' => $this->t('Processing data cleanup...'),
      ];
      batch_set($batch);
    }
    else {
      $this->messenger()->addMessage($this->t('No orphaned data found to clean up.'));
    }
  }

}
