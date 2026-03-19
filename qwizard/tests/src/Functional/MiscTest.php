<?php

namespace Drupal\Tests\qwizard\Functional;

use Drupal\Core\Url;
use Drupal\qwizard\QwizardGeneral;
use Drupal\Tests\BrowserTestBase;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group qwizard
 */
class MiscTest {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['qwizard'];

  public static function testQwizQuestions($qwiz_id, $num) {

    $storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    $qwiz = $storage->load($qwiz_id);
    $questions = $qwiz->getQuestionIds();
    $tquestions = $qwiz->getTestQuestions();
  }

  public static function getQuestionInfoForClasses() {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', "classes");
    $tids = $query->execute();
    $terms = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);
    foreach ($terms as $class) {
      $questions[$class->name->value] = QwizardGeneral::getAllQuestionIdsForClass($class);
    }
    dpm($questions);
  }

}
