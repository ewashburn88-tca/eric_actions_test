<?php

namespace Drupal\qwizard\Entity;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\qwizard\QwizardGeneral;

/**
 * Defines the Quiz Snapshot entity.
 *
 * @ingroup qwizard
 *
 * @ContentEntityType(
 *   id = "qwiz_snapshot",
 *   label = @Translation("Quiz Snapshot"),
 *   handlers = {
 *   },
 *   base_table = "qwiz_snapshot",
 *   admin_permission = "administer quiz results entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "label" = "id",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log"
 *   },
 * )
 */
class QwizSnapshot extends ContentEntityBase implements QwizSnapshotInterface {
  private array $questions_by_vid = [];
  /**
   * Default structure of qwiz snapshot array.
   *
   * @var array
   */
  private static $emptySnapshotArray = [
    'qwiz_id'              => 0,
    'qwiz_rev'             => 0,
    'user_id'              => 0,
    'subscription_id'      => 0,
    'last_question_viewed' => 0, // Provides starting point when opening.
    'questions'            => [
      //@todo: this could depend on question type.
      0 => [
        'question_id'    => 1234,
        'question_text'  => '',
        'feedback'       => '',
        'chosen_answer'  => '',  // Id of answer user chose
        'correct_answer' => 1, // Id of the correct answer in array.
        'answers'        => [
          // Indexed by answer id
          1 => 'answer_text',
        ],
      ],
    ],
  ];


  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
  }

  public function setPreloadedQuestionsByVID($questions_by_vid){
    $this->questions_by_vid = $questions_by_vid;
  }
  public function getPreloadedQuestionsByVID(){
    return $this->questions_by_vid;
  }

  /**
   * Gets the Quiz Snapshot array.
   *
   * @return array
   */
  public function getSnapshotArray() {
    $snapshot = $this->snapshot->value;
    if (empty($snapshot)) {
      return [];
    }
    $ss_array = Json::decode($snapshot);
    $QWGeneral = \Drupal::service('qwizard.general');
    $TransformTextService = \Drupal::service('qwizard.question_transform_text');
    $enable_snapshots_from_vid = $QWGeneral->getStatics()['enable_snapshots_from_vid'];
    // Add in the question text, feedback and answer text.

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $ss_questions = !empty($ss_array['questions']) ? $ss_array['questions'] : [];

    $question_vids_to_load = [];
    $question_nids_to_load = [];
    $questions_from_nids = [];
    $questions_from_vids = [];
    foreach ($ss_questions as $idx => $ss_question) {
      if(empty($ss_question['question_id'])) continue;
      $nid = $ss_question['question_id'];
      $question_nids_to_load[$nid] = $nid;

      $vid = $ss_question['question_vid'];
      $question_vids_to_load[$vid] = $vid;
    }

    if($enable_snapshots_from_vid) {
      $preloaded_questions = $this->getPreloadedQuestionsByVID();
      if (!empty($preloaded_questions)) {
        $questions_from_vids = $preloaded_questions;
      } else {
        $questions_from_vids = $node_storage->loadMultipleRevisions($question_vids_to_load);
      }
    }

    foreach($questions_from_vids as $question){
      $nid = $question->id();
      $questions_from_nids[$nid] = $question;
      if(!empty($question_nids_to_load[$nid])){
        unset($question_nids_to_load[$nid]);
      }
    }

    if(!empty($question_nids_to_load)){
      $questions_from_nids_combined = array_merge($node_storage->loadMultiple($question_nids_to_load), $questions_from_nids);
      foreach($questions_from_nids_combined as $question){

      //  $lang_code = \Drupal::languageManager()->getCurrentLanguage()->getId();
      //  $question = $question->hasTranslation($lang_code) ? $question->getTranslation($lang_code) : $question;

        $questions_from_nids[$question->id()] = $question;
      }
    }
    # var_dump($question_vids_to_load);
    #var_dump($questions_from_vids);


    $current_lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    foreach ($ss_questions as $idx => $ss_question) {
      $question = null;
      if(!empty($questions_from_vids[$ss_question['question_vid']])) {
        $question = $questions_from_vids[$ss_question['question_vid']];
      }
      // Not all questions have same vid as old site, so load by nid if not.
      if (empty($question) && !empty( $questions_from_nids[$ss_question['question_id']])) {
        $question = $questions_from_nids[$ss_question['question_id']];
      }
      if(empty($question)){
        continue;
      }

      if($question->hasTranslation($current_lang)) {
        $question = $question->getTranslation($current_lang);
      }

      // Add question type
      if (!empty($ss_question['question_id']) && empty($ss_question['question_type'])) {
        $ss_array['questions'][$idx]['question_type'] = NULL;
        $question_node = $question;

        if (!empty($question_node)) {
          $ss_array['questions'][$idx]['question_type'] = $question_node->bundle();
        }
      }

      if (empty($question) || $question->bundle() != 'qw_simple_choice') {
        continue;
      }

      $ss_array['questions'][$idx]['question_text'] = $TransformTextService->transformQuizText($question->field_question->value, $question, 'field_question');
      if ($question->field_feedback) {
        $ss_array['questions'][$idx]['feedback'] = $TransformTextService->transformQuizText($question->field_feedback->value, $question, 'field_feedback');
      }
      if ($question->field_reference) {
        $ss_array['questions'][$idx]['reference'] = $TransformTextService->transformQuizText($question->field_reference->value, $question, 'field_reference');
      }
      $answer_text                                       = [];
      $answer_text[$question->field_answer_correct->aid] = $TransformTextService->transformQuizText($question->field_answer_correct->value, $question, 'field_answer');

      foreach ($question->field_answers_incorrect as $item) {
        $answer_text[$item->aid] = $TransformTextService->transformQuizText($item->value, $question, 'field_answer');
      }
      $ss_array['questions'][$idx]['answers'] = $answer_text;

      if (empty($ss_array['questions'][$idx]['answers_order']) && !empty($ss_array['questions'][$idx]['answers'])) {
        $alpha = range('A', 'Z');
        foreach ($ss_array['questions'][$idx]['answers'] as $id => $text) {
          $letter                                                = array_shift($alpha);
          $ss_array['questions'][$idx]['answers_order'][$letter] = $id;
        }
      }
    }
    return $ss_array;
  }

  /**
   * Use this to transform strings inside quiz's
   *
   * @param $str
   *
   * @return string

  private static function transformQuizText($str) {
    $QWGeneral = \Drupal::service('qwizard.general');
    $str = $QWGeneral->transformRootRelativeUrlsToAbsolute($str);
    $str = $QWGeneral->transformMerckLinks($str);

    return $str;
  }
   *  */

  /**
   * Gets the Quiz Snapshot json.
   *
   * @return string
   *   Snapshot as json.
   */
  public function getSnapshotJson() {
    return $this->snapshot->value;
  }

  /**
   * Sets and validates the Quiz Snapshot.
   *
   * @param string|array $json
   *   The Quiz Snapshot json string or array.
   */
  public function setSnapshot($json) {
    if (is_string($json)) {
      $json = Json::decode($json);
    }

    // Remove extra data that we do not want in database
    $fields_to_unset = ['question_text', 'feedback', 'answers'];
    if(!empty($json['questions'])){
      foreach($fields_to_unset as $field) {
        foreach ($json['questions'] as &$question_data) {
          if (isset($question_data[$field])) {
            unset($question_data[$field]);
          }
        }
      }
    }

    $json = Json::encode($json);

    $this->set('snapshot', $json);

    return $json;
  }

  /**
   * Formats and sets the question array to proper structure.
   *
   * This is the primary function to set up a 'test-take'. It takes an array of
   * question ids and builds the test snapshot array. This array is recorded as
   * a json field in the qwSnapshot entity and referenced to the QwizResult.
   * From that point, everything done with this 'test-take' will rely on this
   * array.
   *
   * @param array $question_ids
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @todo: Part of this function belongs in the question type submodule because
   *      it needs to translate it's own structure into this array.
   *
   * @see $emptySnapshotArray
   *
   */
  public static function buildSnapshotQuestionsArray(array $question_ids, $shuffle = TRUE) {
    /* @todo: This is dependant on question format, so part of this should be
     * in submodule and get called by question type.
     */

    $node_storage       = \Drupal::entityTypeManager()->getStorage('node');
    $TransformTextService = \Drupal::service('qwizard.question_transform_text');
    $snapshot_questions = [];
    $loaded_nodes = $node_storage->loadMultiple($question_ids);
    $current_lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    foreach ($question_ids as $idx => $id) {
      // Build the snapshot.
      // @todo: From here ******** should be in question type submodule.
      if (!empty($loaded_nodes[$id])) {
        $node = $loaded_nodes[$id];
        if($node->hasTranslation($current_lang)) {
          $node = $node->getTranslation($current_lang);
        }

        $snapshot_questions[$idx] = [
          'question_id'    => $id,
          'qId'    => $id,
          'question_vid'   => $node->get('vid')->value,
          'chosen_answer'  => 0,
          'correct_answer' => $node->field_answer_correct->aid,
        ];
        $answers                  = [];
        $answers[]                = $node->field_answer_correct->aid;

        if ($node->field_answers_incorrect) {
          foreach ($node->get('field_answers_incorrect') as $item) {
            $answers[$item->aid] = $item->aid;
          }
        }

        if ($node->field_question) {
          $snapshot_questions[$idx]['question_text'] = $TransformTextService->transformQuizText($node->field_question->value, $node, 'field_question');
        }

        if ($node->field_feedback) {
          $snapshot_questions[$idx]['feedback'] = $TransformTextService->transformQuizText($node->field_feedback->value, $node, 'field_feedback');
        }

        if ($node->field_reference) {
          $snapshot_questions[$idx]['reference'] = $TransformTextService->transformQuizText($node->field_reference->value, $node, 'field_reference');
        }

        if (!empty($node->field_question_tables->value)) {
          $tables = $node->get('field_question_tables')->getValue();
          foreach ($tables as $tidx => $table) {
            $snapshot_questions[$idx]['tables'][$tidx] = $TransformTextService->transformQuizText($table['value'], $node, 'table', 'field_question_tables');
          }
        }

        $answer_text                                   = [];
        $answer_text[$node->field_answer_correct->aid] = $TransformTextService->transformQuizText($node->field_answer_correct->value, $node, 'field_answer');
        if(!empty($node->field_answers_incorrect)) {
          foreach ($node->field_answers_incorrect as $val) {
            $answer_text[$val->aid] = $TransformTextService->transformQuizText($val->value, $node, 'field_answer');
          }
        }

        if ($shuffle) {
          // This is where the answer order for each test take is randomized.
          $shuffled = \Drupal\qwizard\Randomizer::shuffleAssoc($answer_text);
          if (!$shuffled) {
            // @todo: Handle error.
            throw new \Exception();
          }
        }
        $snapshot_questions[$idx]['answers'] = $answer_text;

        // Add the order array.
        $i        = 0;
        $alphabet = range('A', 'Z');
        foreach ($answer_text as $aid => $text) {
          $snapshot_questions[$idx]['answers_order'][$alphabet[$i++]] = $aid;
        }
      }
      else {
        // The question isn't available any longer.
        // @todo: Got to do something better here.
      }
      // @todo: To here ******** should be in question type submodule.
    }
    return $snapshot_questions;
  }

  /**
   * Returns an empty array with the structure of a snapshot.
   *
   * @return array
   */
  public static function getEmptySnapshotArray() {
    return self::$emptySnapshotArray;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The entity ID of this agreement.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields[$entity_type->getKey('uuid')] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    $fields['snapshot'] = BaseFieldDefinition::create('jsonb')
      ->setLabel(t('Quiz Snapshot'))
      ->setDescription(t('JSON Array of all questions and answers exactly as they were when the test was taken.'))
      ->setDisplayOptions('view', [
        'label'  => 'above',
        'weight' => 4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
