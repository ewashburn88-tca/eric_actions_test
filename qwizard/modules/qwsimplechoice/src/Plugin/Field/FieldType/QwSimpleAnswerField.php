<?php

namespace Drupal\qwsimplechoice\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'qw_simple_answer_field' field type.
 *
 * @FieldType(
 *   id = "qw_simple_answer_field",
 *   label = @Translation("Quiz Wizard Simple Answer field"),
 *   description = @Translation("Quiz Wizard simple answer field text area with format & aid."),
 *   category = @Translation("Quiz Wizard"),
 *   default_widget = "qw_simple_answer_widget",
 *   default_formatter = "qw_simple_answer_formatter"
 * )
 */
class QwSimpleAnswerField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['aid'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Answer Id'));

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Text value'));

    $properties['format'] = DataDefinition::create('filter_format')
      ->setLabel(new TranslatableMarkup('Text format'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'aid' => [
          'description' => 'A unique id for the answer.',
          'type' => 'serial',
          'not null' => FALSE,
        ],
        'value' => [
          'type' => 'text',
          'size' => 'big',
          'not null' => FALSE,
        ],
        'format' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => FALSE,
        ],
      ],
      'indexes' => [
        'format' => ['format'],
        'aid'    => ['aid'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = [];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $isEmpty = empty($this->get('aid')->getValue()) &&
      empty($this->get('value')->getValue()) &&
      empty($this->get('format')->getValue());
    return $isEmpty;
  }

}
