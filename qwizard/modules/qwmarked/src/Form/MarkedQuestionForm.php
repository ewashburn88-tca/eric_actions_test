<?php

namespace Drupal\qwmarked\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the marked_question entity edit forms.
 */
class MarkedQuestionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $id = ['%id' => $this->entity->id()];
    //$logger_arguments = $message_arguments + ['link' => render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New Marked Question %id has been created.', $id));
      $this->logger('qwmarked')->notice('Created new Marked Question %id', $id);
    }
    else {
      $this->messenger()->addStatus($this->t('The Marked Question %id has been updated.', $id));
      $this->logger('qwmarked')->notice('Updated new Marked Question %id.', $id);
    }

    $form_state->setRedirect('entity.marked_question.canonical', ['marked_question' => $entity->id()]);
  }

}
