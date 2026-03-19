<?php

namespace Drupal\qwsimplechoice\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;

/**
 * Class QuestionForm.
 */
class QuestionForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'question_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $question = NULL) {

    //$form = parent::buildForm($form, $form_state);

    $form['#attached']['library'][] = 'qwsimplechoice/question_form';

    if ($question->getType() != 'qwsimplechoice') {
      // @todo: Not correct type do something.
    }

    $answer_array = [];
    $answer_array['correct'] = $question->field_answer_correct->value;
    foreach ($question->get('field_answers_incorrect')->getValue() as $item) {
      $answer_array[] = $item['value'];
    }

    qwsimplechoice_shuffle_assoc($answer_array);

    $form['question'] = [
      '#type'       => 'html_tag',
      '#tag'        => 'div',
      '#value'      => $question->field_question->value,
      '#weight'     => -10,
      '#attributes' => [
        'class' => ['question'],
        'id'    => ['qwsimplechoice-question'],
      ],
    ];

    // Store feedback html.
    $form['feedback_html'] = [
      '#type'  => 'value',
      '#value' => $question->field_feedback->value,
    ];
    // Store answers array.
    $form['answer_array'] = [
      '#type'  => 'value',
      '#value' => $answer_array,
    ];

    $form['ajax_block'] = [
      '#type'       => 'container',
      '#attributes' => [
        'class' => ['ajax-container', 'qw-feedback-answer'],
        'id'    => ['qwsimplechoice-ajax-container'],
      ],
    ];
    $form['ajax_block']['feedback_display'] = [
      '#type'       => 'html_tag',
      '#tag'        => 'div',
      '#value'      => '',
      '#attributes' => [
        'class' => ['feedback-hidden'],
        'id'    => ['qwsimplechoice-feedback'],
      ],
    ];
    $form['ajax_block']['answers'] = [
      '#type'     => 'radios',
      '#title'    => '',
      '#required' => FALSE,
      '#options'  => $answer_array,
    ];
    $form['ajax_block']['submit_answer'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('Final Answer'),
      '#name'   => 'final_answer',
      '#access' => TRUE,
      '#ajax'   => [
        'callback' => '::showFeedback',
        'method'   => 'replace',
        'wrapper'  => 'qwsimplechoice-ajax-container',
        'progress' => [
          'type'    => 'throbber',
          'message' => t('Checking...'),
        ],
      ],
    ];

    return $form;
  }

  /**
   * Returns ajax_block on callback.
   *
   * @param array                                $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function showFeedback(array &$form, FormStateInterface $form_state): array {
    $values = $form_state->getValues();
    // Choice response prefix.
    // @see: QwsimplechoiceConfigForm
    $config = \Drupal::config('qwsimplechoice.qwsimplechoiceconfig');
    $result = '<p class="incorrect">' . $config->get('feedback_incorrect_response') . '</p>';
    if ($values['answers'] == 'correct') {
      $result = '<p class="correct">' . $config->get('feedback_correct_response') . '</p>';
    }
    $feedback           = $result . $values['feedback_html'];
    $form['ajax_block'] = [
      '#type'            => 'container',
      '#attributes'      => [
        'class' => ['ajax-container', 'qw-feedback-answer'],
        'id'    => ['qwsimplechoice-ajax-container'],
      ],
      'feedback_display' => [
        '#type'       => 'html_tag',
        '#tag'        => 'div',
        '#value'      => $feedback,
        '#weight'     => '0',
        '#attributes' => [
          'class' => ['feedback'],
          'id'    => ['qwsimplechoice-feedback'],
        ],
      ],
    ];

    foreach ($values['answer_array'] as $key => $answer) {
      $key = (string) $key;
      // Strip outer <p> tags.
      $answer   = preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/i', '$1', $answer);
      $response = $answer;
      if ($key == 'correct') {
        // Add class to the correct answer div.
        $answer_class = 'correct';
        if ($values['answers'] == 'correct') {
          // Add suffix to the correct answer if chosen.
          // @see: QwsimplechoiceConfigForm
          $choice_indicator = $config->get('answer_correct_suffix');
          $response         = $answer . ' - <span class="choice-indicator">(' . $choice_indicator . ')</span>';
        }
      }
      elseif ($key == $values['answers']) {
        // Add suffix to the incorrect answer if chosen.
        // @see: QwsimplechoiceConfigForm
        $choice_indicator = $config->get('answer_incorrect_suffix');
        $response         = $answer . ' - <span class="choice-indicator">(' . $choice_indicator . ')</span>';
        $answer_class     = 'incorrect';
      }
      else {
        $answer_class = 'not-chosen';
      }
      $form['ajax_block']['feedback_answers_label'] = [
        '#type'       => 'html_tag',
        '#tag'        => 'div',
        '#value'      => $this->t('Answers'),
        //'#weight'     => -10,
        '#attributes' => [
          'class' => ['answers-in-feedback'],
        ],
      ];
      $form['ajax_block']['feedback_answers'][$key] = [
        '#type'       => 'html_tag',
        '#tag'        => 'div',
        '#value'      => $response,
        '#attributes' => [
          'class' => ['feedback-answer', $answer_class],
        ],
      ];
    }

    return $form['ajax_block'];
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
    $form_state->setRebuild();
  }
}
