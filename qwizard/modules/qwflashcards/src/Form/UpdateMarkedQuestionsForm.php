<?php
namespace Drupal\qwflashcards\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class UpdateMarkedQuestionsForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'update_marked_questions';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {


    $form['markup'] = [
      '#markup'=>'<p>Updated Marked Questions</p>',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['clear_import_queue_ui']['queue_ui'] = [
      '#type'   => 'markup',
      '#markup' => "<p><a target='_blank' href='/admin/config/system/queue-ui'>Queue UI. Use to reset queue or run all</a></p>"
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $nids            = \Drupal::entityQuery('profile')
      ->exists('field_marked_questions')
      // @todo remove this and handle in the processor
      ->condition('field_marked_questions', '%u0022%', 'NOT LIKE')
      ->execute();


    foreach($nids as $nid){
      $queue_name = 'update_marked_questions_queue_worker';
      $queue = \Drupal::queue($queue_name);
      $queue->createQueue();

      $result = $queue->createItem($nid);
      $queue_id = $queue_name . '-' . $nid;
      \Drupal::state()->set($queue_id, $result);
    }

    dpm(count($nids).' items added to queue');
  }
}

