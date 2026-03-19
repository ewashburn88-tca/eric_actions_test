<?php

namespace Drupal\qwsimplechoice\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'qw_simple_answer_widget' widget.
 *
 * @FieldWidget(
 *   id = "qw_simple_answer_widget",
 *   label = @Translation("Quiz Wizard Simple Answer widget"),
 *   field_types = {
 *     "qw_simple_answer_field"
 *   }
 * )
 */
class QwSimpleAnswerWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'rows' => '5',
        'placeholder' => '',
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['rows'] = [
      '#type' => 'number',
      '#title' => t('Rows'),
      '#default_value' => $this->getSetting('rows'),
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

    $summary[] = t('Number of rows: @rows', ['@rows' => $this->getSetting('rows')]);
    $placeholder = $this->getSetting('placeholder');
    if (!empty($placeholder)) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['aid'] = [
      '#type' => 'value',
      '#value' => $items[$delta]->aid,
    ];
    if(empty($items[$delta]->value)){
      $element['aid']['#value'] = null;
    }

    $element['value'] = [
        '#type' => 'textarea', //'#type' => 'text_format',
        '#title' => $element['#title'] . ' ' . $this->t('Text'),
        '#default_value' => empty($items[$delta]->value) ? '' : $items[$delta]->value,
        '#rows' => $this->getSetting('rows'),
        '#placeholder' => $this->getSetting('placeholder'),
      ];

    $element['format'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Text Format'),
      '#default_value' => isset($items[$delta]->format) ? $items[$delta]->format : 'editor_html',
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => $this->getFieldSetting('max_length'),
    ];

    return $element;
  }

  /**
   * @inheritdoc
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $key=>$value) {
      if(empty($value['value'])){
        unset($values[$key]);
      }
      if(!intval($value['aid'])){
        $value['aid'] = null;
      }
    }

    /*
     * Commenting this out, presave can do this instead
     foreach ($values as &$value) {
      if(!isset($value['aid'])) {
         $max = max(array_column($values, 'aid'));
         if(is_numeric($max)) {
           $value['aid'] = (string)($max + 1);
         }
       }
     }
    */
    return parent::massageFormValues($values, $form, $form_state);
  }

}
