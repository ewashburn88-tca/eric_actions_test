<?php

namespace Drupal\qwarchive\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\qwarchive\QwArchiveRecordManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Restore requests list callbacks.
 */
class QwRestoreRequests extends ControllerBase {

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The qwarchive record manager.
   */
  protected QwArchiveRecordManager $qwArchiveRecordManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->qwArchiveRecordManager = $container->get('qwarchive.record_manager');

    return $instance;
  }

  /**
   * Add tag to the question.
   */
  public function buildList() {
    $build = [];
    // Get records with support for pager with 50 records per page.
    $records = $this->qwArchiveRecordManager->getRestoreRequests([], 50);
    // User storage to be used later.
    $user_storage = $this->entityTypeManager()->getStorage('user');
    // Record status.
    $record_statuses = $this->qwArchiveRecordManager->getRestoreStatuses();

    // Table header.
    $header = [
      'uid' => $this->t('User'),
      'created' => $this->t('Created'),
      'status' => $this->t('Status'),
      'comment' => $this->t('Comment'),
    ];
    $rows = [];
    foreach ($records as $record) {
      $uid = $record['uid'];
      $account = $user_storage->load($uid);
      $user_link = Link::fromTextAndUrl($account->getDisplayName(), $account->toUrl())->toString();

      $row = [];
      // User link.
      $row['uid'] = $user_link;
      $row['created'] = $this->dateFormatter->format($record['created'], 'short');
      $row['status'] = $record_statuses[$record['status']];
      $row['comment'] = !empty($record['comment']) ? $record['comment'] : '-';

      $rows[] = $row;
    }
    $build[] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No restore requests available yet.'),
      '#weight' => 0,
    ];
    $build[] = [
      '#type' => 'pager',
      '#weight' => 1,
    ];
    return $build;
  }

}
