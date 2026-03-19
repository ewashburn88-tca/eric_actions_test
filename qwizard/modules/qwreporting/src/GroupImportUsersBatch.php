<?php

namespace Drupal\qwreporting;

/**
 * Batch callbacks for importing users into group.
 */
class GroupImportUsersBatch {

  /**
   * Batch callback to import the user to group.
   */
  public static function importUser($record, $group_id, &$context) {
    $group = \Drupal::service('qwreporting.groups')->getGroup($group_id);

    $context['message'] = t('Importing user into @label.', [
      '@label' => $group->label(),
    ]);

    $uid = NULL;
    $account = NULL;
    if (!empty($record['uid'])) {
      $uid = $record['uid'];
      // Load the user.
      $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    }
    elseif (!empty($record['email'])) {
      $uid = $record['email'];
      $account = user_load_by_mail($uid );
    }
    elseif (!empty($record['username'])) {
      $uid = user_load_by_name($uid );
    }

    if (!empty($account)) {
      // Check if there is mismatch between uid, email or username.
      $email = $account->getEmail();
      if (!empty($record['email']) && $record['email'] != $email) {
        \Drupal::logger('group_user_import')->warning('Found mismatched email for @uid. Expected @email but @email_given is provided.', [
          '@uid' => $uid,
          '@email' => $email,
          '@email_given' => $record['email'],
        ]);
        \Drupal::messenger()->addWarning(t('Found mismatched email for @uid. Expected @email but @email_given is provided.', [
          '@uid' => $uid,
          '@email' => $email,
          '@email_given' => $record['email'],
        ]));
      }
      $username = $account->getAccountName();
      if (!empty($record['username']) && $record['username'] != $username) {
        \Drupal::logger('group_user_import')->warning('Found mismatched username for @uid. Expected @name but @name_given is provided.', [
          '@uid' => $uid,
          '@name' => $username,
          '@name_given' => $record['username'],
        ]);
        // Also show the message.
        \Drupal::messenger()->addWarning(t('Found mismatched username for @uid. Expected @name but @name_given is provided.', [
          '@uid' => $uid,
          '@name' => $username,
          '@name_given' => $record['username'],
        ]));
      }

      $students = $group->get('field_students')->getValue();
      // Let's add the student into group.
      $students[] = ['target_id' => $account->id()];
      $group->set('field_students', $students);
      $group->save();
    }
    else {
      $context['message'] = t('Unable to import the user into @label. The User does not exists.', [
        '@label' => $group->label(),
      ]);
      // The given user does not exist. Add a log entry.
      \Drupal::logger('group_user_import')->warning('Unable to import user @uid into the group @label. The user does not exists.', [
        '@uid' => $uid,
        '@label' => $group->label(),
      ]);
      \Drupal::messenger()->addWarning(t('Unable to import user @uid into the group @label. The user does not exists.', [
        '@uid' => $uid,
        '@label' => $group->label(),
      ]));
    }
  }

  /**
   * Batch finished callback.
   */
  public static function importUsersFinished($success, $results, array $operations) {
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
