<?php

namespace Drupal\qwizard\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\qwizard\Entity\QwPoolInterface;

/**
 * Class QwPoolController.
 *
 *  Returns responses for Question Pool routes.
 */
class QwPoolController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Displays a Question Pool  revision.
   *
   * @param int $qwpool_revision
   *   The Question Pool  revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($qwpool_revision) {
    $qwpool = $this->EntityTypeManager()->getStorage('qwpool')->loadRevision($qwpool_revision);
    $view_builder = $this->EntityTypeManager()->getViewBuilder('qwpool');

    return $view_builder->view($qwpool);
  }

  /**
   * Page title callback for a Question Pool  revision.
   *
   * @param int $qwpool_revision
   *   The Question Pool  revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($qwpool_revision) {
    $qwpool = $this->EntityTypeManager()->getStorage('qwpool')->loadRevision($qwpool_revision);
    return $this->t('Revision of %title from %date', ['%title' => $qwpool->label(), '%date' => format_date($qwpool->getRevisionCreationTime())]);
  }

  /**
   * Generates an overview table of older revisions of a Question Pool .
   *
   * @param \Drupal\qwizard\Entity\QwPoolInterface $qwpool
   *   A Question Pool  object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(QwPoolInterface $qwpool) {
    $account = $this->currentUser();
    $langcode = $qwpool->language()->getId();
    $langname = $qwpool->language()->getName();
    $languages = $qwpool->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $qwpool_storage = $this->EntityTypeManager()->getStorage('qwpool');

    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $qwpool->label()]) : $this->t('Revisions for %title', ['%title' => $qwpool->label()]);
    $header = [$this->t('Revision'), $this->t('Operations')];

    $revert_permission = (($account->hasPermission("revert all question pool revisions") || $account->hasPermission('administer question pool entities')));
    $delete_permission = (($account->hasPermission("delete all question pool revisions") || $account->hasPermission('administer question pool entities')));

    $rows = [];

    $vids = $qwpool_storage->revisionIds($qwpool);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\qwizard\QwPoolInterface $revision */
      $revision = $qwpool_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = \Drupal::service('date.formatter')->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $qwpool->getRevisionId()) {
          $link = $this->l($date, new Url('entity.qwpool.revision', ['qwpool' => $qwpool->id(), 'qwpool_revision' => $vid]));
        }
        else {
          $link = $qwpool->link($date);
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
              'url' => Url::fromRoute('entity.qwpool.revision_revert', ['qwpool' => $qwpool->id(), 'qwpool_revision' => $vid]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.qwpool.revision_delete', ['qwpool' => $qwpool->id(), 'qwpool_revision' => $vid]),
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

    $build['qwpool_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
