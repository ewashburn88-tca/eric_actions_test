<?php

namespace Drupal\qwizard\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\qwizard\Entity\QwizInterface;

/**
 * Interface QwizTakeFormInterface.
 */
interface QwizTakeFormInterface extends FormInterface {


  /**
   * {@inheritdoc}
   */
  public function getFormId();

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, QwizInterface $qwiz = NULL);

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state);

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state);

}
