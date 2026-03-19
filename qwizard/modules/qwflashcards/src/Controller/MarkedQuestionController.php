<?php

namespace Drupal\qwflashcards\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Drupal\social_media_links\Plugin\SocialMediaLinks\Platform\Drupal;
use Drupal\user\Entity\User;

/**
 * Returns responses for marked question routes.
 */
class MarkedQuestionController extends ControllerBase {

  public function updateMarkedQuestions() {
    $ops = [];

    $nids            = \Drupal::entityQuery('profile')
    ->exists('field_marked_questions')
      // @todo remove this and handle in the processor
    //->condition('field_marked_questions', '%u0022%', 'NOT LIKE')
    ->execute();

    foreach($nids as $key => $value) {
      $ops[] = [
        '\Drupal\qwflashcards\ProcessMarkedQuestionToCards::processUpdateMarkedQuestions',
        [$value],
      ];
    }

    $batch = [
      'title'      => t('Update Marked Questions'),
      'operations' => $ops,
      'finished'   => '\Drupal\qwflashcards\ProcessMarkedQuestionToCards::finishUpdateMarkedQuestions',
    ];

    batch_set($batch);
    return batch_process('admin');
  }
}
