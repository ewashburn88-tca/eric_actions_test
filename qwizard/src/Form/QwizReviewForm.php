<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
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
class QwizReviewForm extends QwizTakeForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwiz_review_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, QwizInterface $qwiz = NULL, $length = NULL, $qwiz_result = NULL) {
    $form = parent::buildForm($form, $form_state, $qwiz, $length, $qwiz_result);
    // Get only completed questions.
    $form['t_actions']['complete_test']['#value'] = $this->t('Close Review');
    $form['t_actions']['complete_test']['#name'] = 'close_review';

    $snapshot = $qwiz_result->snapshot->entity;
    $ss_array = $snapshot->getSnapshotArray();
    $counts = array_count_values($ss_array['question_summary']);
    $skipped = $counts['skipped'];
    $form['stats']['skipped']['#value'] = $this->t('Skipped: ') . $skipped;
    return $form;
  }

  public function submitPrevQuestion(array &$form, FormStateInterface $form_state) {
    parent::submitPrevQuestion($form, $form_state);
  }

  public function submitNextQuestion(array &$form, FormStateInterface $form_state) {
    parent::submitNextQuestion($form, $form_state);
  }

  public function submitGoToQuestion(array &$form, FormStateInterface $form_state) {
    parent::submitGoToQuestion($form, $form_state);
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
    $qwiz_result = $this->store->get('quizResult');
    $qwiz_result->setReviewedTime(time());
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

}
