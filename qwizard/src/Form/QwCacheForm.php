<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class QwCacheForm.
 */
class QwCacheForm extends ConfigFormBase {

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames() {
        return [
            'qwizard.cache',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'qwizard_cache_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('qwizard.qwizardsettings');

        $form['welcome_message'] = [
            '#markup' => '<div>Welcome to the Quiz Wizard Admin Area</div>',
        ];

        $form['getTotalQuizzes'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('getTotalQuizzes Cache'),
            '#description' => $this->t(''),
            '#default_value' => 1,
        ];

      $form['quizResultsCache'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('quizResultsCache Cache'),
        '#description' => $this->t(''),
        '#default_value' => 1,
      ];

      $form['getOthersProgress'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('getOthersProgress Cache'),
        '#description' => $this->t(''),
        '#default_value' => 1,
      ];



        $form['help']['description'] = [
          '#type'   => 'markup',
          '#markup' => "<p><a target='_blank' href='/devel/state'>View Current Queue</a></p>"
        ];

        $form['help']['queue_ui'] = [
          '#type'   => 'markup',
          '#markup' => "<p><a target='_blank' href='/admin/config/system/queue-ui'>Queue UI. Use to reset queue or run all</a></p>"
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
      $options = $form_state->getValues();
      $QwCache = \Drupal::service('qwizard.cache');
      $QwCache->generateQwCacheQueue($options);

    }
}
