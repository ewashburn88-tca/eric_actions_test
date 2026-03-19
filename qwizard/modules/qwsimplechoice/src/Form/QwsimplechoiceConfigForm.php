<?php

namespace Drupal\qwsimplechoice\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QwsimplechoiceConfigForm.
 */
class QwsimplechoiceConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'qwsimplechoice.qwsimplechoiceconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwsimplechoice_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('qwsimplechoice.qwsimplechoiceconfig');
    $form['feedback_correct_response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Feedback Correct Response'),
      '#description' => $this->t('Statement to student above feedback when answered correctly.'),
      '#default_value' => $config->get('feedback_correct_response'),
    ];
    $form['feedback_incorrect_response'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Feedback Incorrect Response'),
      '#description' => $this->t('Statement to student above feedback when answered incorrectly'),
      '#default_value' => $config->get('feedback_incorrect_response'),
    ];
    $form['answer_correct_suffix'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Answer Correct Suffix'),
      '#description' => $this->t('Text that appears after the answer to indicate students correct choice.'),
      '#default_value' => $config->get('answer_correct_suffix'),
    ];
    $form['answer_incorrect_suffix'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Answer Incorrect Suffix'),
      '#description' => $this->t('Text that appears after the answer to indicate the students incorrect choice.'),
      '#default_value' => $config->get('answer_incorrect_suffix'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('qwsimplechoice.qwsimplechoiceconfig')
      ->set('feedback_correct_response', $form_state->getValue('feedback_correct_response'))
      ->set('feedback_incorrect_response', $form_state->getValue('feedback_incorrect_response'))
      ->set('answer_correct_suffix', $form_state->getValue('answer_correct_suffix'))
      ->set('answer_incorrect_suffix', $form_state->getValue('answer_incorrect_suffix'))
      ->save();
  }

}
