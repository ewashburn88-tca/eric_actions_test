<?php

namespace Drupal\qwizard\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'qwanswer_field' field type.
 *
 * @FieldType(
 *   id = "qwanswer_field",
 *   label = @Translation("Quiz Wizard Answer field"),
 *   description = @Translation("Quiz Wizard answer field text area with format, aid, points, is_correct."),
 *   category = @Translation("Quiz Wizard"),
 *   default_widget = "qwanswer_widget",
 *   default_formatter = "qwanswer_formatter"
 * )
 */
class QwAnswerField extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['aid'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Answer Id'))
      ->setRequired(TRUE);

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Text value'))
      ->setSetting('case_sensitive', $field_definition->getSetting('case_sensitive'))
      ->setRequired(TRUE);

    $properties['format'] = DataDefinition::create('filter_format')
      ->setLabel(new TranslatableMarkup('Text format'));

    $properties['points'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Text value'))
      ->setDescription(t('How many points is this question worth.'));

    $properties['is_correct'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Is Correct?'))
      ->setDescription(t('Flag to indicate this answer is a correct response.'));

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
        'points' => [
          'description' => 'Points awarded for when this answer is correct.',
          'type' => 'int',
          'unsigned' => TRUE,
          'default' => 1,
        ],
        'is_correct' => [
          'type' => 'int',
          'size' => 'tiny',
          'default' => 0,
        ],
      ],
      'indexes' => [
        'format' => ['format'],
        'aid'    => ['aid'],
        'is_correct'    => ['is_correct'],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    if ($max_length = $this->getSetting('max_length')) {
      $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
      $constraints[] = $constraint_manager->create('ComplexData', [
        'value' => [
          'Length' => [
            'max' => $max_length,
            'maxMessage' => t('%name: may not be longer than @max characters.', [
              '%name' => $this->getFieldDefinition()->getLabel(),
              '@max' => $max_length
            ]),
          ],
        ],
      ]);
    }

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['value'] = $random->word(mt_rand(1, $field_definition->getSetting('max_length')));
    return $values;
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
      empty($this->get('format')->getValue()) &&
      empty($this->get('points')->getValue()) &&
      empty($this->get('is_correct')->getValue());
    return $isEmpty;
  }

}
