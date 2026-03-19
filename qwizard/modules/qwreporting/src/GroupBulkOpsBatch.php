<?php

namespace Drupal\qwreporting;

/**
 * Batch callbacks for group operations.
 */
class GroupBulkOpsBatch {

  /**
   * Batch callback to change account status.
   */
  public static function changeAccountStatus($account, $status, &$context) {
    $status_label = $status ? t('Enabling') : t('Disabling');
    $context['message'] = t('@label status for user %name',
      [
        '@label' => $status_label,
        '%name' => $account->getAccountName(),
      ]
    );
    \Drupal::service('qwreporting.group_ops')->changeUserAccountStatus($account, $status);
  }

  /**
   * Batch callback to log user out.
   */
  public static function logoutUser($account, &$context) {
    $context['message'] = t('Logging out user %name',
      [
        '%name' => $account->getAccountName(),
      ]
    );
    \Drupal::service('qwreporting.group_ops')->logoutUser($account);
  }

  /**
   * Batch callback to end subscription of the user.
   */
  public static function endSubscription($account, $course_id, &$context) {
    $context['message'] = t('Ending subscription of user %name',
      [
        '%name' => $account->getAccountName(),
      ]
    );
    \Drupal::service('qwreporting.group_ops')->endSubscription($account, $course_id);
  }

  /**
   * Batch callback to extend subscription of the user.
   */
  public static function extendSubscription($account, $value, $course_id, $type, &$context) {
    $context['message'] = t('Extending subscription of user %name',
      [
        '%name' => $account->getAccountName(),
      ]
    );
    \Drupal::service('qwreporting.group_ops')->extendSubscription($account, $value, $course_id, $type);
  }

  /**
   * Batch callback to change account premium status.
   */
  public static function changeAccountPremium($account, $premium, $course_id, &$context) {
    $context['message'] = t('Changing premium status of user %name',
      [
        '%name' => $account->getAccountName(),
      ]
    );
    \Drupal::service('qwreporting.group_ops')->changeAccountPremium($account, $premium, $course_id);
  }

  /**
   * Batch callback to change account special status.
   */
  public static function changeAccountSpecial($account, $special, &$context) {
    $context['message'] = t('Changing special status of user %name',
      [
        '%name' => $account->getAccountName(),
      ]
    );
    \Drupal::service('qwreporting.group_ops')->changeAccountSpecial($account, $special);
  }

  /**
   * Batch finished callback.
   */
  public static function groupOpsBatchFinished($success, $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('Finished performing group operations successfully.'));
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
