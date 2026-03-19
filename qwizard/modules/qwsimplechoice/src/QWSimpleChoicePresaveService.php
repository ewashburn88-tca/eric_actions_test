<?php

namespace Drupal\qwsimplechoice;

class QWSimpleChoicePresaveService{
  protected int $max_aid;


  /**
   * Given a node, will give it AID's on any answers without it
   * @param $node
   * @return mixed
   */
  public function updateAIDs(&$node){
    $this->getHighestAID();

    $this->setAIDs($node, 'field_answers_incorrect');
    $this->setAIDs($node, 'field_answer_correct');
    return $node;
  }

  /**
   * Gets and sets the current highest answer ID that is in use
   * @return void
   */
  private function getHighestAID(){
    $incorrect_highest = \Drupal::database()->query('SELECT MAX(field_answers_incorrect_aid) FROM {node_revision__field_answers_incorrect}')->fetchField();
    $correct_highest = \Drupal::database()->query('SELECT MAX(field_answer_correct_aid) FROM {node_revision__field_answer_correct}')->fetchField();

    $highest_value = $correct_highest;
    if($incorrect_highest > $correct_highest){
      $highest_value = $incorrect_highest;
    }
    $this->max_aid = $highest_value;
  }

  /**
   * Given a node and field name, will give an AID to any empty answers
   */
  private function setAIDs(&$node, $field_name) {
    if(empty($this->max_aid)){
      // ideally this is called earlier, this is just here as a guard
      $this->getHighestAID();
    }

    $current_aid_values = $node->get($field_name)->getValue();
    $was_changed = false;
    foreach($current_aid_values as $key=>$value){
      if(!empty($value['value']) && empty($value['aid'])){
        $this->max_aid = $this->max_aid + 1;
        $current_aid_values[$key]['aid'] = $this->max_aid;
        $was_changed = true;
      }
    }

    if($was_changed){
      $node->set($field_name, $current_aid_values);
    }

    return $node;
  }
}
