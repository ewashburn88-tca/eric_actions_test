<?php

namespace Drupal\qwarchive;

/**
 * Batch callbacks for Qwizard archive restore.
 */
class QwArchiveRestoreBatch {

  /**
   * Batch callback to restore user data.
   */
  public static function restoreUserData($uid, $type, &$context) {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    $context['message'] = t('Restoring data for %name',
      ['%name' => $account->getDisplayName()]
    );
    $data_type_callbacks = \Drupal::service('qwarchive.restore_manager')->getDataTypeCallbacks();
    // By default, keep result as false for all.
    $context['results']['data_process'][$uid] = [
      'uid' => $uid,
    ];

    $callback = $data_type_callbacks[$type];
    try {
      $process_status = \Drupal::service('qwarchive.restore_manager')->$callback($account);
      // Status should have been updated in callback itself.
      $context['results']['data_process'][$uid]['success_processes'][$type] = [
        'status' => $process_status,
        'message' => 'Success',
      ];
    }
    catch (\Throwable $e) {
      $message = $e->getMessage();
      \Drupal::logger('qwarchive')->error("Data restore for user $uid for $type failed with error: $message");
      $context['results']['data_process'][$uid]['failed_processes'][$type] = [
        'status' => FALSE,
        'message' => 'Encountered error: ' . $message,
      ];
    }
  }

  /**
   * Batch finished callback for user data restore.
   */
  public static function restoreUserDataFinished($success, $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      if (!empty($results['data_process'])) {
        $messenger->addMessage(t('Processed data for @count user(s).', [
          '@count' => count($results['data_process']),
        ]));
        foreach ($results['data_process'] as $data_process) {
          $uid = $data_process['uid'];
          $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
          $failed_processes = !empty($data_process['failed_processes']) ? $data_process['failed_processes'] : [];
          $failed_items = [];
          foreach ($failed_processes as $data_type => $process_item) {
            $failed_items[] = $data_type;
          }
          if (!empty($failed_items)) {
            $messenger->addError(t('Data restore failed for %name: %types.', [
              '%name' => $account->getDisplayName(),
              '%types' => implode(', ', $failed_items),
            ]));
          }
          else {
            // All items are successful.
            \Drupal::service('qwarchive.record_manager')->removeRecord($uid, TRUE);
          }
        }
      }
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $messenger->addError(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
