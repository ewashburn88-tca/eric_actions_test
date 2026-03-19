<?php
/**
 * @file
 * Contains Drupal\qwizard\Form\AdminForm.
 */
namespace Drupal\welcome\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class AdminForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'qwizard.adminsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwizard_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('qwizard.adminsettings');

    $form['welcome_message'] = [
      '#markup' => '<div>Welcome to the Quiz Wizard Admin Area</div>',
    ];

    $contentTypes = \Drupal::service('entity_type.manager')->getStorage('node_type')->loadMultiple();

    $contentTypesList = [];
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
    }

    $form['question_types'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Select Question Types'),
      '#description' => $this->t('Select node types that are questions for the Quiz Wizard module.'),
      '#options' => $contentTypesList,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('qwizard. adminsettings')
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();
  }

}
