<?php

namespace Drupal\qwizard\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Url;
use Drupal\qwizard\Entity\QwizInterface;
use Drupal\qwizard\Entity\QwizResult;
use Drupal\qwizard\Entity\QwPool;
use Drupal\qwizard\Entity\QwPoolInterface;
use Drupal\qwizard\QwizSessionHandler;
use Drupal\qwsubs\SubscriptionHandler;
use Drupal\user\UserInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\qwsubs\Entity\Subscription;

/**
 * Class QwizController.
 *
 *  Returns responses for Quiz routes.
 */
class QwizController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Quiz  revision.
   *
   * @param int $qwiz_revision
   *   The Quiz  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($qwiz_revision) {
    $qwiz = $this->EntityTypeManager()->getStorage('qwiz')->loadRevision($qwiz_revision);
    $view_builder = $this->EntityTypeManager()->getViewBuilder('qwiz');

    return $view_builder->view($qwiz);
  }

  /**
   * Page title callback for a Quiz  revision.
   *
   * @param int $qwiz_revision
   *   The Quiz  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($qwiz_revision) {
    $qwiz = $this->EntityTypeManager()->getStorage('qwiz')->loadRevision($qwiz_revision);
    return $this->t('Revision of %title from %date', ['%title' => $qwiz->label(), '%date' => format_date($qwiz->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Quiz .
   *
   * @param \Drupal\qwizard\Entity\QwizInterface $qwiz
   *   A Quiz  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(QwizInterface $qwiz) {
    $account = $this->currentUser();
    $langcode = $qwiz->language()->getId();
    $langname = $qwiz->language()->getName();
    $languages = $qwiz->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $qwiz_storage = $this->EntityTypeManager()->getStorage('qwiz');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $qwiz->label()]) : $this->t('Revisions for %title', ['%title' => $qwiz->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all quiz revisions") || $account->hasPermission('administer quiz entities')));
    $delete_permission = (($account->hasPermission("delete all quiz revisions") || $account->hasPermission('administer quiz entities')));

    $rows = [];

    $vids = $qwiz_storage->revisionIds($qwiz);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\qwizard\QwizInterface $revision */
      $revision = $qwiz_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $qwiz->getRevisionId()) {
          $link = $this->l($date, new Url('entity.qwiz.revision', ['qwiz' => $qwiz->id(), 'qwiz_revision' => $vid]));
        }
        else {
          $link = $qwiz->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => \Drupal::service('renderer')->renderPlain($username),
              'message' => ['#markup' => $revision->getRevisionLogMessage(), '#allowed_tags' => Xss::getHtmlTagList()],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => Url::fromRoute('entity.qwiz.revision_revert', ['qwiz' => $qwiz->id(), 'qwiz_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.qwiz.revision_delete', ['qwiz' => $qwiz->id(), 'qwiz_revision' => $vid]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['qwiz_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

  /**
   * Initializes a quiz result and starts a quiz.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface|NULL $qwiz
   * @param int|NULL                                  $length
   *    Number of questions in quiz session.
   * @param bool                                      $api
   *    Is this an API (REST) request.
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function initializeQuiz(QwizInterface $qwiz = NULL, $length = NULL, $alt_type = 'standard') {
    $qwiz_result = QwizSessionHandler::initializeQuiz($qwiz, $length, $alt_type);
    // Send to tester (quiz take form/page).
    $url = Url::fromRoute('qwizard.qwiz_take_form', ['qwiz_result' => $qwiz_result->id()])->toString();
    return new RedirectResponse($url);
  }

  /**
   * Implements quiz results page.
   *
   * @param \Drupal\qwizard\Entity\QwizInterface|NULL $qwiz
   * @param \Drupal\user\UserInterface|NULL           $user
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function quizResults(QwizInterface $qwiz = NULL, UserInterface $user = NULL) {

    $quiz_total = $qwiz->getQuestionCount();
    $quiz_correct = 0;//$this->getCorrect();

    $page = [];
    $page['heading'] = [
      '#type'   => 'markup',
      '#markup' => '<h1 class="quiz-heading">' . $this->t('Your test results for @testname', ['@testname' => $qwiz->getName()]) . ' </h1>',
    ];
    $page['stats'] = [
      '#type'       => 'container',
      '#attributes' => [
        'class' => 'stats',
      ],
    ];
    $page['stats']['quiz_heading'] = [
      '#type'   => 'markup',
      '#markup' => '<span class="quiz-heading">' . $this->t('Progress: ') . ' </span>',
    ];
    $page['stats']['quiz'] = [
      '#type'   => 'markup',
      '#markup' => '<span class="quiz-stat quiz-total">' . $this->t(' Questions in quiz @quiz_total ', ['@quiz_total' => $quiz_total]) . ' </span>',
    ];

    $page['stats']['progress_in_quiz'] = [
      '#type'   => 'markup',
      '#markup' => '<span class="quiz-stat quiz-complete">' . $this->t(' Questions completed @quiz_complete ', ['@quiz_complete' => $quiz_correct]) . ' </span><br>',
    ];
    $page['take_10'] = [
      '#type' => 'link',
      '#title' => $this->t('Take 10 Question Test'),
      '#url' => Url::fromRoute('qwizard.initialize_quiz', ['qwiz' => $qwiz->id(), 'length' => 10]),
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-take-button'),
        'id' => array(''),
      ]
    ];
    $page['take_60'] = [
      '#type' => 'link',
      '#title' => $this->t('Take Continuous Test'),
      '#url' => Url::fromRoute('qwizard.initialize_quiz', ['qwiz' => $qwiz->id(), 'length' => 60]),
      '#weight' => '0',
      '#attributes' => [
        'class' => array('qwiz-take-button'),
        'id' => array(''),
      ]
    ];

    $qwiz_results = QwizResult::getAllResultsForQwiz($qwiz, $user);
    $header = [
      $this->t('Date'),
      $this->t('Score'),
      $this->t('Grade'),
      $this->t('Review'),
      $this->t('Reviewed'),
    ];
    $results_list= [];
    foreach ($qwiz_results as $idx => $result) {
      $date_iso = $result->start->value;
      $tdate= new \DateTime($date_iso);
      $date_formatted = $tdate->format('m-d-y H:i');
      $results_list[$idx]['date'] = $date_formatted;
      $seen = $result->seen->value;
      $attempted = $result->attempted->value;
      $correct = $result->correct->value;

      $results_list[$idx]['score'] = $attempted == 0 ? 0 : $correct . '/' . $attempted;
      $results_list[$idx]['grade'] = $attempted == 0 ? 0 : number_format($correct / $attempted * 100, 1) . '%';
      $review_link = Url::fromRoute('qwizard.qwiz_review_form', ['qwiz_result' => $result->id()]);
      $results_list[$idx]['review'] = $this->l('Review', $review_link);
      $rdate_iso = $result->reviewed->value;
      if (empty($rdate_iso)) $rdate_formatted = '';
      else {
        $rdate = new \DateTime($rdate_iso);
        $rdate_formatted = $rdate->format('m-d-y H:i');
      }
      $results_list[$idx]['reviewed'] = $rdate_formatted;
    }

    $page['results_table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Your test results for @testname', ['@testname' => $qwiz->getName()]),
      '#header' => $header,
      '#rows' => $results_list,
    ];

    $page['#attached']['library'][] = 'qwizard/qwiz_results';

    return $page;
  }

}
