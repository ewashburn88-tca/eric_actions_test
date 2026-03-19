<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qwizard\Entity\Qwiz;
use Drupal\qwizard\Entity\QwizInterface;
use Drupal\qwizard\Entity\QwizResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

/**
 * Class QwizTakeForm.
 */
class QwizTakeForm extends FormBase {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /** user.private_tempstore
   * @var \Drupal\Core\Session\SessionManagerInterface
   */
  private $sessionManager;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $store;

  protected $question_nids;
  protected $current_question_number;
  protected $module_path;
  protected $length;

  /**
   * QwizTakeForm constructor.
   *
   * @param \Drupal\user\PrivateTempStoreFactory         $temp_store_factory
   * @param \Drupal\Core\Session\SessionManagerInterface $session_manager
   * @param \Drupal\Core\Session\AccountInterface        $current_user
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, SessionManagerInterface $session_manager, AccountInterface $current_user) {
    $this->tempStoreFactory = $temp_store_factory;
    $this->sessionManager = $session_manager;
    $this->currentUser = $current_user;

    // @todo: Do we even need the store for members since their data is always
    // recorded with each answered? Might need it for non members still, user's
    // whose data isn't recorded.
    $this->store = $this->tempStoreFactory->get('qwiz_data');

    //$this->store->set('current_question_complete', FALSE);
    //$this->store->set('chosen_answer_id', 0);
    //$this->store->set('correct_answer_id', 0);
    //$this->store->set('current_question_id', 0);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('session_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwiz_take_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, QwizInterface $qwiz = NULL, $length = NULL, $qwiz_result = NULL) {
    // Start a manual session for anonymous users.
    // This would be for the site sample test/s.
    // @todo: Implement this:
    if ($this->currentUser->isAnonymous() && !isset($_SESSION['qwiz_form_session'])) {
      $_SESSION['qwiz_form_session'] = true;
      $this->sessionManager->start();
    }
    $qwiz_id = $qwiz_result->getQuizId();
    $pool_type = Qwiz::qwizGetPoolType($qwiz_id);
    // @todo: Default pool type needs to be setting or pool type required.
    if (empty ($pool_type)) $pool_type = 'decr_correct'; // Default.

    $form['#attached']['library'][] = 'qwizard/qwiz_take_form';
    $form['#prefix'] = '<div id="ajax-container">';
    $form['#suffix'] = '</div>';

    $module_handler = \Drupal::service('module_handler');
    $this->module_path = $module_handler->getModule('qwizard')->getPath();

    $this->store->set('quizResult', $qwiz_result);

    // Reload the $qwiz_result for ajax calls.
    if ($form_state->isRebuilding()) {
      $storage     = \Drupal::entityTypeManager()->getStorage('qwiz_result');
      $qwiz_result = $storage->load($qwiz_result->id());
    }

    $snapshot = $qwiz_result->snapshot->entity;

    $ss_array = $snapshot->getSnapshotArray();

    // Now we have the whole test in an array, just need to display correctly.
    // @todo: Snapshot questions array has changed, this needs updating.
    $this->question_nids = array_keys($ss_array['questions']);

    // Determine current question id.
    $current_question_idx = $this->store->get('current_question_id');
    // If $current_question_idx is empty, we are starting or restarting a quiz
    // or the $current_question_idx isn't in the questions array, reset.
    if (empty($current_question_idx) || !in_array($current_question_idx, $this->question_nids)) {
      // Figure out which question we should be on.
      if (empty($ss_array['last_question_viewed'])) {
        // No last question viewed, so goto first.
        $current_question_idx = reset($this->question_nids);
      }
      else {
        $current_question_idx = $ss_array['questions'][$ss_array['last_question_viewed']];
      }
    }
    $this->store->set('current_question_id', $current_question_idx);
    $this->current_question_number = array_search($current_question_idx, $this->question_nids) + 1;
    // Save this value.
    $form['question_number'] = array(
      '#type' => 'value',
      '#value' => $this->current_question_number,
    );

    $answered = $qwiz_result->attempted->value;
    $answered = empty($answered) ? 0 : $answered;
    $seen = $qwiz_result->seen->value;
    $skipped = $seen - $answered;
    $time = 0;
    $this->length = $qwiz_result->total_questions->value;
    $remaining = $this->length - $answered;
    $progress = $answered / $this->length * 100;

    // Question section ********************************************************
    $form['qwrapper'] = [
      '#type' => 'container',
      '#weight' => 0,
      '#attributes' => [
        'class' => array('question-wrapper'),
      ]
    ];
    $form['qwrapper']['question'] = [
      '#type'       => 'html_tag',
      '#tag'        => 'div',
      '#value'      => $ss_array['questions'][$current_question_idx]['question_text'],
      '#weight'     => -10,
      '#attributes' => [
        'class' => ['question-text'],
        'id'    => ['the-question'],
      ],
    ];

    $form['qwrapper']['ajax_answer_container']                     = [
      '#type'       => 'container',
      '#attributes' => [
        'class' => ['ajax-container', 'qw-feedback-answer'],
      ],
    ];
    $correct_answer_id = $ss_array['questions'][$current_question_idx]['correct_answer'];
    $chosen_answer_id = $ss_array["questions"][$current_question_idx]["chosen_answer"];
    $answer_ids = array_keys($ss_array["questions"][$current_question_idx]['answers']);
    // Once a test is completed, the question array is updated and any questions
    // not answered will be marked as 'skipped', so during test review all answers
    // should be indicated as complete.
    $current_question_complete = $chosen_answer_id === 'skipped' || in_array($chosen_answer_id, $answer_ids);
    // @todo: Don't know that using pool type is good for generic form, maybe
    //  this should be over-ridden or at least made a settings choice.
    if ($current_question_complete && $pool_type != 'exam') {
      // Choice response prefix.
      // @todo: this needs to be moved from QwsimplechoiceConfigForm to qwizard module
      $config = \Drupal::config('qwsimplechoice.qwsimplechoiceconfig');
      $result = '<p class="incorrect">' . $config->get('feedback_incorrect_response') . '</p>';
      if ($chosen_answer_id == $correct_answer_id) {
        $result = '<p class="correct">' . $config->get('feedback_correct_response') . '</p>';
      }
      elseif ($chosen_answer_id == 'skipped') {
        $result = '<p class="skipped">You skipped this question.</p>';
      }
      $feedback = $result . $ss_array['questions'][$current_question_idx]['feedback'];
      $form['ajax_answer_container'] = [
        '#type'            => 'container',
        '#attributes'      => [
          'class' => ['ajax-container', 'qw-feedback-answer'],
          'id'    => ['ajax-container'],
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

      foreach ($ss_array['questions'][$current_question_idx]['answers'] as $key => $answer) {
        $key = (string) $key;
        // Strip outer <p> tags.
        $answer   = preg_replace('/<p[^>]*>(.*)<\/p[^>]*>/i', '$1', $answer);
        $response = $answer;
        if ($key == $correct_answer_id) {
          // Add class to the correct answer div.
          $answer_class = 'correct';
          if ($chosen_answer_id == $correct_answer_id) {
            // Add suffix to the correct answer if chosen.
            // @see: QwsimplechoiceConfigForm
            $choice_indicator = $config->get('answer_correct_suffix');
            $response         = $answer . ' - <span class="choice-indicator">(' . $choice_indicator . ')</span>';
          }
        }
        elseif ($key == $chosen_answer_id) {
          // Add suffix to the incorrect answer if chosen.
          // @see: QwsimplechoiceConfigForm
          $choice_indicator = $config->get('answer_incorrect_suffix');
          $response         = $answer . ' - <span class="choice-indicator">(' . $choice_indicator . ')</span>';
          $answer_class     = 'incorrect';
        }
        else {
          $answer_class = 'not-chosen';
        }
        $form['ajax_answer_container']['feedback_answers_label'] = [
          '#type'       => 'html_tag',
          '#tag'        => 'div',
          '#value'      => $this->t('Answers'),
          //'#weight'     => -10,
          '#attributes' => [
            'class' => ['answers-in-feedback'],
          ],
        ];
        $form['ajax_answer_container']['feedback_answers'][$key] = [
          '#type'       => 'html_tag',
          '#tag'        => 'div',
          '#value'      => $response,
          '#attributes' => [
            'class' => ['feedback-answer', $answer_class],
          ],
        ];
      }

    }
    else {
      $form['qwrapper']['ajax_answer_container']['answers'] = [
        '#type'     => 'radios',
        '#title'    => '',
        '#required' => FALSE,
        '#default_value' => $chosen_answer_id ? $chosen_answer_id : 0,
        '#disabled' => $current_question_complete,
        '#options'  => $ss_array['questions'][$current_question_idx]['answers'],
      ];
      // This should implement it's own handlers.
      $form['qwrapper']['ajax_answer_container']['submit_answer'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Final Answer'),
        '#name'   => 'submit_answer',
        '#access' => TRUE,
        '#ajax'   => [
          'callback' => '::showFeedback',
          'method'   => 'replace',
          'wrapper'  => 'ajax-container',
          'progress' => [
            'type'    => 'throbber',
            'message' => t('Checking...'),
          ],
        ],
        '#submit' => array([$this, 'submitAnswer']),
      ];
    }
    // End question section ****************************************************

    $form['t_actions'] = [
      '#type' => 'actions',
      '#weight' => -10,
      '#attributes' => [
        'class' => array('qwiz-actions-test'),
        'id' => array(''),
      ]
    ];
    $form['t_actions']['back_button'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Back'),
      //'#type' => 'image_button',
      //'#src' => $this->module_path . '/images/icons/000000/chevron-left.svg',
      //'#title' => $this->t('Back'),
      '#name'   => 'back_button',
      '#weight' => 0,
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ],
      '#submit' => array([$this, 'submitPrevQuestion']),
    ];
    /*$form['t_actions']['jump_to'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Go to question:'),
      '#default_value' => '',
      '#size' => 3,
      '#maxlength' => 3,
    ];*/

    // Setup for the question bar. This is a 10 question sliding button bar.
    $start = $this->current_question_number - 5;
    $start = $start < 1 ? 1 : $start;
    $end = $this->current_question_number + 4;
    $start = $end == 10 ? 1 : $start;
    $end = $end < 10 ? 10 : $end;
    $end = $end > $this->length ? $this->length : $end;
    $start = $end == $this->length ? $start = $this->length - 9 : $start;
    for ($num = $start; $num <= $end; $num++) {
      $result_class = empty($ss_array['question_summary'][$this->question_nids[$num - 1]]) ? '' : $ss_array['question_summary'][$this->question_nids[$num - 1]] . '-question';
      $current_class = ($num == $this->current_question_number) ? 'current-question' : 'not-current-question';
      $form['t_actions']['jump_to_button' . $num] = [
        '#type'       => 'submit',
        '#value'      => $num,
        '#name'       => 'jump-to',
        '#weight'     => $num,
        '#attributes' => [
          'class' => [
            'qwiz-actions-button',
            $result_class,
            $current_class,
          ],
          'id'    => ['question-button-' . $num],
        ],
        '#submit'     => ['::submitGoToQuestion'],
      ];
    }
    $form['t_actions']['next_button'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Next'),
      //'#type' => 'image_button',
      //'#src' => $this->module_path . '/images/icons/000000/chevron-right.svg',
      //'#title' => $this->t('Next'),
      '#name'   => 'next_button',
      '#weight' => ++$num,
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ],
      '#submit' => ['::submitNextQuestion'],
    ];

    // This uses the form submit function.
    $form['t_actions']['complete_test'] = [
      '#type'       => 'submit',
      '#value'      => $this->t('Complete Test'),
      //'#type' => 'image_button',
      //'#src' => $this->module_path . '/images/icons/000000/icons8-intelligence-26.png',
      //'#title' => $this->t('Complete Test'),
      '#name'   => 'complete_test',
      '#weight' => ++$num,
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ]
    ];

    $form['stats'] = [
      '#type'           => 'container',
      '#weight'         => -20,
      '#attributes'     => [
        'class' => array('row'),
        'id'    => array(''),
      ],
      'question_number' => [
        '#type'       => 'html_tag',
        '#tag'        => 'span',
        '#title'      => $this->t('Question Number'),
        '#value'      => $this->t('Question Number: ') . $this->current_question_number,
        '#weight'     => '0',
        '#attributes' => [
          'class' => array('column'),
          'id'    => array('question-number'),
        ],
      ],
      'answered'        => [
        '#type'       => 'html_tag',
        '#tag'        => 'span',
        '#title'      => $this->t('Questions Answered'),
        '#value'      => $this->t('Questions Answered: ') . $answered,
        '#weight'     => '0',
        '#attributes' => [
          'class' => array('column'),
          'id'    => array('answered'),
        ],
      ],
      'remaining'       => [
        '#type'       => 'html_tag',
        '#tag'        => 'span',
        '#title'      => $this->t('Remaining'),
        '#value'      => $this->t('Remaining: ') . $remaining,
        '#weight'     => '0',
        '#attributes' => [
          'class' => array('column'),
          'id'    => array('remaining'),
        ],
      ],
      'skipped'         => [
        '#type'       => 'html_tag',
        '#tag'        => 'span',
        '#title'      => $this->t('Skipped'),
        '#value'      => $this->t('Skipped: ') . $skipped,
        '#weight'     => '0',
        '#attributes' => [
          'class' => array('column'),
          'id'    => array('skipped'),
        ],
      ],
    ];
    $form['time']  = [
      '#type'       => 'html_tag',
      '#tag'        => 'span',
      '#title'      => $this->t('Time'),
      '#value'      => $this->t('Time: ') . $time,
      '#weight'     => '0',
      '#access' => FALSE,  // @todo: this needs to be controlled
      '#attributes' => [
        'class' => array('column'),
        'id'    => array('time'),
      ],
    ];
    // @todo: Find better way to implement this.
    /*$form['progress'] = [
      '#markup' => '<div class="widget gsc-progress text-light">
            <div class="">Progress (Questions answered)</div>
             <div class="progress">
               <div class="progress-bar" data-progress-animation=' . $progress . '% style="width: ' . $progress . '%;">
                  <span class="percentage">' . $progress . '%</span>
               </div>
            </div>
         </div>',
      '#weight' => -50,
    ];*/

    $this->questionActionsSection($form, $form_state);

    return $form;
  }

  protected function questionActionsSection(&$form, &$form_state) {
    $form['q_actions'] = [
      '#type' => 'actions',
      '#weight' => -10,
      '#attributes' => [
        'class' => array('qwiz-actions-question'),
        'id' => array(''),
      ]
    ];
    $form['q_actions']['mark'] = [
      '#type' => 'image_button',
      '#src' => $this->module_path . '/images/icons/000000/paintbrush.svg',
      '#title' => $this->t('Mark'),
      '#default_value' => 'mark',
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ]
    ];
    $form['q_actions']['report_a_problem'] = [
      '#type' => 'image_button',
      '#src' => $this->module_path . '/images/icons/000000/wrench.svg',
      '#title' => $this->t('Report a Problem'),
      '#default_value' => 'report',
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ]
    ];
    $form['q_actions']['overview'] = [
      '#type' => 'image_button',
      '#src' => $this->module_path . '/images/icons/000000/puzzlepiece.svg',
      '#title' => $this->t('Overview'),
      '#default_value' => 'overview',
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ]
    ];
    $form['q_actions']['lab_values'] = [
      '#type' => 'image_button',
      '#src' => $this->module_path . '/images/icons/000000/barchart.svg',
      '#title' => $this->t('Lab Values'),
      '#default_value' => 'lab',
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ]
    ];
    $form['q_actions']['definitions'] = [
      '#type' => 'image_button',
      '#src' => $this->module_path . '/images/icons/000000/questionmark-disc.svg',
      '#title' => $this->t('Definitions'),
      '#default_value' => 'definitions',
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-actions-button'),
        'id' => array(''),
      ]
    ];
  }

  /**
   * Returns ajax_answer_container on callback.
   *
   * @param array                                $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function showFeedback(array &$form, FormStateInterface $form_state): array {
    return $form;
  }

  public function submitAnswer(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!empty($values['answers'])) {
      $this->store->set('chosen_answer_id', $values['answers']);
      $this->store->set('current_question_complete', TRUE);
      // Score this response.
      $qwiz_result         = $this->store->get('quizResult');
      $current_question_idx = $this->store->get('current_question_id');

      $qwiz_result->scoreQuestion($values['answers'], $current_question_idx);

      // Update the store.
      $this->store->set('quizResult', $qwiz_result);

      $form_state->setRebuild();
    }
  }

  public function submitPrevQuestion(array &$form, FormStateInterface $form_state) {
    $this->recordQuestion($form_state);

    // Redirect to previous question.
    // Change $current_question_idx
    $prev_question_id = $this->getRelativeQuestionId(-1);
    $this->store->set('current_question_id', $prev_question_id);

    //$form_state->setRebuild();
  }

  public function submitNextQuestion(array &$form, FormStateInterface $form_state) {
    $this->recordQuestion($form_state);

    // Redirect to next question.
    // Change $current_question_idx
    $next_question_id = $this->getRelativeQuestionId(1);
    $this->store->set('current_question_id', $next_question_id);

    //$form_state->setRebuild();
  }

  public function submitGoToQuestion(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->recordQuestion($form_state);

    // Redirect to given question number.
    // Change $current_question_idx
    $current_question_idx = $this->store->get('current_question_id');
    $goto_qnum = $values['jump-to'];
    $current_qnum = $values['question_number'];
    $offset = $goto_qnum - $current_qnum;
    $next_question_id = $this->getRelativeQuestionId($offset);
    $this->store->set('current_question_id', $next_question_id);

    //$form_state->setRebuild();
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
    // This function will close the test.

    $this->recordQuestion($form_state);

    // Record the test closed.
    $qwiz_result = $this->store->get('quizResult');
    $qwiz_result->endQwizResult();
    $qwiz_result->save();



    // Delete the store items.
    $this->store->delete('current_question_complete');
    $this->store->delete('chosen_answer_id');
    $this->store->delete('correct_answer_id');
    $this->store->delete('current_question_id');
    $this->store->delete('quizResult');

    // @todo: not sure why, but on initial submit, currentUser is null, in this
    // case load.
    if (empty($this->currentUser)) {
      $current_user = \Drupal::currentUser();
      $this->currentUser = $current_user;
    }
    // Redirect to the quiz results page.
    $params = [
      'qwiz' => $qwiz_result->qwiz_id->value,
      'user' => $this->currentUser->id(),
      ];
    $url = Url::fromRoute('qwizard.quiz_results', $params);
    $form_state->setRedirectUrl($url);
  }

  protected function recordQuestion(FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // First score the response. Even if they didn't select an answer, we need
    // need to record as a skip.
    $qwiz_result = $this->store->get('quizResult');
    $current_question_idx = $this->store->get('current_question_id');

    if (isset($values['answers'])) {
      $qwiz_result->scoreQuestion($values['answers'], $current_question_idx);
    }

    // Update the store.
    $this->store->set('quizResult', $qwiz_result);

    return $current_question_idx;
  }

  public function getRelativeQuestionId($offset = 0) {
    // Current question id.
    $current_question_idx = $this->store->get('current_question_id');
    if ($offset == 0) {
      return $current_question_idx;
    }

    $key = array_search($current_question_idx, $this->question_nids);
    // Since this is a non-indexed array, just add or subtract from key.
    $new_key = $key + $offset;
    $new_key = $new_key < 0 ? 0 : $new_key;
    if ($new_key > sizeof($this->question_nids) -1) return end($this->question_nids);
    return $this->question_nids[$new_key];
  }
}
