<?php

namespace Drupal\qwarchive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\qwarchive\QwArchiveRecordManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * QWArchive list callbacks.
 */
class QwArchiveList extends ControllerBase {

  /**
   * The module list.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The file url generator.
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $qwArchiveRecordManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->moduleList = $container->get('extension.list.module');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->qwArchiveRecordManager = $container->get('qwarchive.record_manager');

    return $instance;
  }

  /**
   * Add tag to the question.
   */
  public function buildList() {
    $build = [];
    // Get records with support for pager with 50 records per page.
    $records = $this->qwArchiveRecordManager->getRecords([], 50);
    // User storage to be used later.
    $user_storage = $this->entityTypeManager()->getStorage('user');
    // Record status.
    $record_statuses = $this->qwArchiveRecordManager->getStatuses();
    // Data type status.
    $record_type_statuses = $this->qwArchiveRecordManager->getDataTypeStatuses();
    // Archive data types.
    $archive_data_types = [
      'student_result', 'qwiz_result', 'qwiz_pools', 'subscriptions',
    ];

    $module_path = '/' . $this->moduleList->getPath('qwarchive');
    $info_icon = $module_path . '/images/info.svg';

    // Table header.
    $header = [
      'uid' => $this->t('User'),
      'student_result' => $this->t('Student Result'),
      'qwiz_result' => $this->t('Quiz Result'),
      'qwiz_pools' => $this->t('Quiz Pools'),
      'subscriptions' => $this->t('Subscriptions'),
      'created' => $this->t('Created'),
      'status' => $this->t('Status'),
      'comment' => $this->t('Comment'),
      'restore' => $this->t('Restore'),
    ];
    $rows = [];
    foreach ($records as $record) {
      $uid = $record['uid'];
      $account = $user_storage->load($uid);
      $user_link = Link::fromTextAndUrl($account->getDisplayName(), $account->toUrl())->toString();

      $row = [];

      // User link.
      $row['uid'] = $user_link;

      // Get items associated with this record.
      $items = $this->qwArchiveRecordManager->getRecordItems($record['id'], 'type');
      foreach ($archive_data_types as $data_type) {
        if (!empty($items[$data_type])) {
          $item = $items[$data_type];

          // Build status with info icon.
          $status_elements = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['archive-status-wrapper', 'archive-font-small'],
            ],
            'status' => [
              '#markup' => $record_type_statuses[$item['status']],
            ],
          ];

          if ($item['status'] != QwArchiveRecordManager::TYPE_STATUS_NOT_ARCHIVED) {
            $status_elements['info'] = [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => [
                'title' => $item['comment'],
                'class' => ['status-info-wrapper'],
              ],
              '#value' => '',
              'icon' => [
                '#theme' => 'image',
                '#uri' => $info_icon,
                '#attributes' => ['class' => ['archive-icon']],
                '#alt' => $this->t('Info'),
              ],
            ];
          }

          $row[$data_type] = [
            'data' => $status_elements,
          ];
        }
        else {
          $row[$data_type] = 'NA';
        }
      }

      $row['created']['data'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['archive-font-small'],
        ],
        'created' => [
          '#markup' => $this->dateFormatter->format($record['created'], 'short'),
        ],
      ];

      $row['status']['data'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['archive-font-small'],
        ],
        'status' => [
          '#markup' => $record_statuses[$record['status']],
        ],
      ];

      $row['comment']['data'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['archive-font-small'],
        ],
        'comment' => [
          '#markup' => !empty($record['comment']) ? $record['comment'] : '-',
        ],
      ];

      $row['restore']['data'] = '-';

      if ($record['status'] == QwArchiveRecordManager::STATUS_COMPLETED) {
        $batch_restore_icon = $module_path . '/images/batch_restore.svg';
        $cron_restore_icon = $module_path . '/images/cron_restore.svg';
        $restore_element = [
          '#type' => 'container',
          'batch_restore' => [
            '#type' => 'link',
            '#title' => [
              '#type' => 'container',
              'icon' => [
                '#theme' => 'image',
                '#uri' => $batch_restore_icon,
                '#attributes' => [
                  'class' => ['archive-icon'],
                  'title' => $this->t('Restore via batch'),
                ],
                '#alt' => $this->t('Restore via batch'),
              ],
            ],
            '#url' => Url::fromRoute('qwarchive.restore', [
              'account' => $account->id(),
              'type' => 'batch',
            ]),
            '#attributes' => ['class' => ['qw-restore-link']],
          ],
          'cron_restore' => [
            '#type' => 'link',
            '#title' => [
              '#type' => 'container',
              'icon' => [
                '#theme' => 'image',
                '#uri' => $cron_restore_icon,
                '#attributes' => [
                  'class' => ['archive-icon'],
                  'title' => $this->t('Restore via cron'),
                ],
                '#alt' => $this->t('Restore via cron'),
              ],
            ],
            '#url' => Url::fromRoute('qwarchive.restore', [
              'account' => $account->id(),
              'type' => 'cron',
            ]),
            '#attributes' => ['class' => ['qw-restore-link']],
          ],
        ];
        // Add restore link.
        $row['restore']['data'] = $restore_element;
      }

      $rows[] = $row;
    }
    $build[] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No user data is archived yet.'),
      '#weight' => 0,
      '#attached' => [
        'library' => ['qwarchive/archive-list'],
      ],
    ];
    $build[] = [
      '#type' => 'pager',
      '#weight' => 1,
    ];
    return $build;
  }

}
