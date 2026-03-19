<?php

namespace Drupal\qwsimplechoice\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Class QuestionForm.
 */
interface QuestionFormInterface extends FormInterface {

  /**
   * {@inheritdoc}
   */
  public function getFormId();

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $question = NULL);

  /**
   * Returns ajax_block on callback.
   *
   * @param array                                $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function showFeedback(array &$form, FormStateInterface $form_state);

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state);

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state);
}
