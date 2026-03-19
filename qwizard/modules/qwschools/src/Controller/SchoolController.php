<?php

namespace Drupal\qwschools\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\qwschools\Entity\SchoolInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SchoolController.
 *
 *  Returns responses for School routes.
 */
class SchoolController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a School revision.
   *
   * @param int $school_revision
   *   The School revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($school_revision) {
    $school = $this->entityTypeManager()->getStorage('school')
      ->loadRevision($school_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('school');

    return $view_builder->view($school);
  }

  /**
   * Page title callback for a School revision.
   *
   * @param int $school_revision
   *   The School revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($school_revision) {
    $school = $this->entityTypeManager()->getStorage('school')
      ->loadRevision($school_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $school->label(),
      '%date' => $this->dateFormatter->format($school->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a School.
   *
   * @param \Drupal\qwschools\Entity\SchoolInterface $school
   *   A School object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(SchoolInterface $school) {
    $account = $this->currentUser();
    $school_storage = $this->entityTypeManager()->getStorage('school');

    $langcode = $school->language()->getId();
    $langname = $school->language()->getName();
    $languages = $school->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $school->label()]) : $this->t('Revisions for %title', ['%title' => $school->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all school revisions") || $account->hasPermission('administer school entities')));
    $delete_permission = (($account->hasPermission("delete all school revisions") || $account->hasPermission('administer school entities')));

    $rows = [];

    $vids = $school_storage->revisionIds($school);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\qwschools\SchoolInterface $revision */
      $revision = $school_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $school->getRevisionId()) {
          $link = $this->l($date, new Url('entity.school.revision', [
            'school' => $school->id(),
            'school_revision' => $vid,
          ]));
        }
        else {
          $link = $school->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
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
              'url' => $has_translations ?
              Url::fromRoute('entity.school.translation_revert', [
                'school' => $school->id(),
                'school_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.school.revision_revert', [
                'school' => $school->id(),
                'school_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.school.revision_delete', [
                'school' => $school->id(),
                'school_revision' => $vid,
              ]),
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

    $build['school_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
