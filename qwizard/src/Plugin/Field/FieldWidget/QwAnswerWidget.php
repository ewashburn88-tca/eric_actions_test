<?php

namespace Drupal\qwizard\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'qwanswer_widget' widget.
 *
 * @FieldWidget(
 *   id = "qwanswer_widget",
 *   label = @Translation("Quiz Wizard Answer widget"),
 *   field_types = {
 *     "qwanswer_field"
 *   }
 * )
 */
class QwAnswerWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => 60,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['size'] = [
      '#type' => 'number',
      '#title' => t('Size of textfield'),
      '#default_value' => $this->getSetting('size'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
$s=1;
    $element['aid'] = [
      '#type' => 'value',
      '#value' => $items[$delta]->aid,
    ];

    $element['value'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Answer Text'),
        '#default_value' => !empty($items[$delta]->value) ? $items[$delta]->value : '',
        '#rows' => $this->getSetting('rows'),
        '#placeholder' => $this->getSetting('placeholder'),
      ];

    $element['format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text Format'),
      '#default_value' => isset($items[$delta]->format) ? $items[$delta]->format : NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
    ];

    $element['is_correct'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This answer is correct.'),
      '#default_value' => !empty($items[0]->is_correct),
    ];

    $element['points'] = [
      '#type' => 'number',
      '#title' => $this->t('How many points awarded for this answer if correct.'),
      '#default_value' => isset($items[$delta]->points) ? $items[$delta]->points : 1,
      '#placeholder' => $this->getSetting('placeholder'),
    ];


    return $element;
  }

}
