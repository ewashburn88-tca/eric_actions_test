<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\qwizard\ClassesHandlerInterface;
use Drupal\qwizard\QwCache;
use Drupal\qwizard\QwizardGeneralInterface;
use Drupal\qwreporting\GroupsInterface;
use Drupal\qwreporting\StudentsInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Route callback for export all results.
 */
class QwreportingExcelExportAllController extends ControllerBase {

  /**
   * The database connection.
   */
  protected Connection $database;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * The kill switch.
   */
  protected KillSwitch $killSwitch;

  /**
   * The qwiz wizard general service.
   */
  protected QwizardGeneralInterface $qwGeneralManager;

  /**
   * The qwreporting.students service.
   */
  protected StudentsInterface $qwreportingStudents;

  /**
   * The qwreporting.groups service.
   */
  protected GroupsInterface $qwreportingGroups;

  /**
   * The qwizard class handler.
   */
  protected ClassesHandlerInterface $qwClassHandler;

  /**
   * The qwizard cache.
   */
  protected QwCache $qwCache;

  /**
   * QwreportingExcelExportAllController constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $kill_switch
   *   The kill switch.
   * @param \Drupal\qwizard\QwizardGeneralInterface $qw_general_manager
   *   The qwiz wizard general service.
   * @param \Drupal\qwreporting\StudentsInterface $qwreporting_students
   *   The qwreporting.students service.
   * @param \Drupal\qwreporting\GroupsInterface $qwreporting_groups
   *   The qwreporting.groups service.
   * @param \Drupal\qwizard\ClassesHandlerInterface $qw_class_handler
   *   The qwizard class handler.
   * @param \Drupal\qwizard\QwCache $qw_cache
   *   The qwizard cache.
   */
  public function __construct(Connection $database, RequestStack $request_stack, KillSwitch $kill_switch, QwizardGeneralInterface $qw_general_manager, StudentsInterface $qwreporting_students, GroupsInterface $qwreporting_groups, ClassesHandlerInterface $qw_class_handler, QwCache $qw_cache) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->killSwitch = $kill_switch;
    $this->qwGeneralManager = $qw_general_manager;
    $this->qwreportingStudents = $qwreporting_students;
    $this->qwreportingGroups = $qwreporting_groups;
    $this->qwClassHandler = $qw_class_handler;
    $this->qwCache = $qw_cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('page_cache_kill_switch'),
      $container->get('qwizard.general'),
      $container->get('qwreporting.students'),
      $container->get('qwreporting.groups'),
      $container->get('qwizard.classeshandler'),
      $container->get('qwizard.cache')
    );
  }

  /**
   * Builds the response as buffer.
   */
  public function build($group) {
    // Trigger the page cache kill switch.
    $this->killSwitch->trigger();

    $group_term = $this->entityTypeManager()->getStorage('taxonomy_term')->load($group);
    if (empty($group_term)) {
      echo $this->t('Unable to load specified group with ID of @group_id', [
        '@group_id' => $group,
      ]);
      exit;
    }

    $groups = $this->qwreportingGroups->getGroups();
    if (empty($groups[$group])) {
      throw new AccessDeniedHttpException();
    }

    $group_data = $this->qwreportingGroups->getGroupData($group);

    $course_id = $group_data['course'];

    $query = $this->requestStack->getCurrentRequest()->query->all();
    $topic_string = $query['topic'] ?? NULL;

    $qwiz_id = NULL;
    $topic_id = NULL;
    $class_id = NULL;
    $is_primary_course = TRUE;
    if (!empty($topic_string)) {
      $qwiz_info = $this->qwGeneralManager->getQwizInfoFromTagString($topic_string);
      if (!empty($qwiz_info)) {
        $qwiz_id = $qwiz_info['qwiz'];
        $topic_id = $qwiz_info['topic'];
        $class_id = $qwiz_info['class'];
        $is_primary_course = $qwiz_info['is_primary_course'];
      }
    }

    $student_data = $this->qwreportingStudents->getStudents($group, NULL, NULL, NULL, $class_id, $qwiz_id, TRUE);

    $quiz_cache_key = 'quizResultsCache_' . $course_id . '_' . $class_id;
    $cache = $this->qwCache->checkCache($quiz_cache_key);
    if (empty($cache['topic_results']) || empty($cache['questions_with_topics'])) {
      $cache = $this->qwCache->buildClassCache($course_id, $class_id);
    }
    $topics = $cache['topic_results'];

    // Remove random topic data.
    if (!empty($topics[224])) {
      unset($topics[224]);
    }

    $filename = $this->t('Qwiz Group Report @group_id', [
      '@group_id' => $group,
    ]);

    // Prepare response object.
    $response = new Response();
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename . '.xlsx');

    // Create an excel.
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Use ASCII values for characters to make it easy for loops & counters.
    // @see getChar of this class. We should have utility class for such things
    // move this functiin there.
    $col = 0;
    $sheet->setCellValue($this->getChar($col) . '1', $this->t('Student Name'));
    $col++;
    $sheet->setCellValue($this->getChar($col) . '1', $this->t('Email'));
    $col++;
    $sheet->setCellValue($this->getChar($col) . '1', $this->t('Overall Progress'));
    $col++;
    $sheet->setCellValue($this->getChar($col) . '1', $this->t('Overall Score'));
    $col++;

    $topic_count = 0;
    if (!empty($topics)) {
      foreach ($topics as $topic_result_data) {
        $topic_progress_label = $this->t('@label % Completed', [
          '@label' => $topic_result_data['label'],
        ]);
        $sheet->setCellValue($this->getChar($col) . '1', $topic_progress_label);
        $col++;
        $topic_score_label = $this->t('@label % Score', [
          '@label' => $topic_result_data['label'],
        ]);
        $sheet->setCellValue($this->getChar($col) . '1', $topic_score_label);
        $col++;
        $topic_count++;
      }
    }

    // Get test mode classes.
    $test_mode_classes = $this->qwGeneralManager->getStatics('test_mode_classes');
    $is_timed_mode_test = in_array($class_id, $test_mode_classes);

    if ($is_timed_mode_test) {
      $sheet->setCellValue($this->getChar($col) . '1', $this->t('Avg. Time per Q'));
      $col++;
    }

    $sheet->setCellValue($this->getChar($col) . '1', $this->t('Last Access'));

    // Add color to header.
    for ($i = 0; $i <= $col; $i++) {
      $sheet->getStyle($this->getChar($i) . '1')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('23dc00');
    }

    // We will center all columns.
    $sheet->getStyle('A:' . $this->getChar($col))
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $last_access_store = [];
    $fill_topics = FALSE;

    // Fetch total test mode duration in seconds for each student natively in SQL.
    $student_durations = [];
    if (!empty($student_data) && !empty($is_primary_course) && $is_timed_mode_test) {
      $student_ids = array_column($student_data, 'id');
      if (!empty($student_ids)) {
        $duration_query = $this->database->select('qwiz_result', 'qr');
        $duration_query->condition('qr.user_id', $student_ids, 'IN');
        $duration_query->condition('qr.course', $course_id);
        $duration_query->condition('qr.class', $class_id);
        $duration_query->isNotNull('qr.start');
        $duration_query->isNotNull('qr.end');
        $duration_query->addExpression(
          "SUM(TIMESTAMPDIFF(SECOND, STR_TO_DATE(qr.start, '%Y-%m-%dT%H:%i:%s'), STR_TO_DATE(qr.end, '%Y-%m-%dT%H:%i:%s')))",
          'total_duration_sec'
        );
        $duration_query->addField('qr', 'user_id');
        $duration_query->groupBy('qr.user_id');

        $student_durations = $duration_query->execute()->fetchAllKeyed(0, 1);
      }
    }

    // Let's add actual data now.
    // Initiate the row counter.
    $row = 2;
    foreach ($student_data as $student) {
      // Intiate last access here so we don't have to do it in both if & else.
      // For now, store it so we can add it at the end of everything.
      $last_access = !empty($student['last_access']) ? date('m/d/Y H:i', $student['last_access']) : $this->t('never');
      $last_access_store[$student['id']] = $last_access;

      // Initiated column counter to start from first one.
      $col = 0;
      $sheet->setCellValue($this->getChar($col) . $row, $student['combined_name']);
      $col++;
      $sheet->setCellValue($this->getChar($col) . $row, $student['email']);
      $col++;

      if (!empty($student['data']['test_mode']['totalQuestion'])) {
        if ($is_primary_course) {
          $overall_total = $student['data']['totalQuestion'];
          $overall_correct = $student['data']['correct'];
          $overall_percent_raw = $overall_total ? ($overall_correct / $overall_total) * 100 : 0;
          $overall_percent = round($overall_percent_raw);
          $overall_out_of = '(' . $overall_correct . '/' . $overall_total . ')';
          $overall_data = $overall_percent . '% ' . $overall_out_of;
          $sheet->setCellValue($this->getChar($col) . $row, $overall_data);
          $col++;
          $overall_score = $student['data']['test_mode']['avg'];
          $sheet->setCellValue($this->getChar($col) . $row, round($overall_score) . '%');
          $col++;
        }
        else {
          $secondary_class = FALSE;
          if (!empty($student['data']['secondary'][$class_id]['qwiz_id'])) {
            $secondary_class = $student['data']['secondary'][$class_id];
          }
          elseif (!empty($student['data']['secondary'][$class_id][$topic_id]['qwiz_id'])) {
            $secondary_class = $student['data']['secondary'][$class_id][$topic_id];
          }
          elseif (!empty($student['data']['secondary'][$class_id][$qwiz_id])) {
            $secondary_class = $student['data']['secondary'][$class_id][$qwiz_id];
          }
          elseif (!empty($student['data']['secondary'][$class_id]) && isset($student['data']['secondary'][$class_id]['totalQuestion']) && $student['data']['secondary'][$class_id]['totalQuestion'] > 0) {
            $secondary_class = $student['data']['secondary'][$class_id];
          }
          elseif (empty($secondary_class) && !empty($student['data']['secondary'][$class_id]) && !isset($student['data']['secondary'][$class_id]['totalQuestion'])) {
            $secondary_class_array = $student['data']['secondary'][$class_id];
            $secondary_class = reset($secondary_class_array);
          }
          if (!empty($secondary_class)) {
            // Sometimes the totalQuestion label can come in different.
            // Account for both totalQuestion & total_questions.
            $q_label = 'totalQuestion';
            if (isset($secondary_class['total_questions'])) {
              $q_label = 'total_questions';
            }
            $overall_total = $secondary_class[$q_label];
            $overall_correct = $secondary_class['correct'];
            $overall_percent_raw = $overall_total ? ($overall_correct / $overall_total) * 100 : 0;
            $overall_percent = round($overall_percent_raw);
            $overall_out_of = '(' . $overall_correct . '/' . $overall_total . ')';
            $overall_data = $overall_percent . '% ' . $overall_out_of;
            $sheet->setCellValue($this->getChar($col) . $row, $overall_data);
            $col++;
            if (empty($secondary_class['avg'])) {
              $secondary_class['avg'] = 0;
            }
            $sheet->setCellValue($this->getChar($col) . $row, round($secondary_class['avg']) . '%');
            $col++;
          }
          else {
            // Move two columns ahead.
            $col += 2;
          }
        }

        // Now add topic data.
        if (!empty($student['other_topics'])) {
          $student_topics = $student['other_topics'];
          foreach ($student_topics as $topic_data) {
            $topic_total = $topic_data['totalQuestion'];
            $topic_correct = $topic_data['correct'];
            $topic_percent_raw = $topic_total ? ($topic_correct / $topic_total) * 100 : 0;
            $topic_percent = round($topic_percent_raw);
            $topic_out_of = '(' . $topic_correct . '/' . $topic_total . ')';
            $topic_score_data = $topic_percent . '% ' . $topic_out_of;
            $sheet->setCellValue($this->getChar($col) . $row, $topic_score_data);
            $col++;

            $topic_score = $topic_data['avg'];
            $sheet->setCellValue($this->getChar($col) . $row, round($topic_score) . '%');
            $col++;
          }
        }
        elseif (!empty($topics)) {
          $fill_topics = TRUE;
        }
        else {
          // Move columns.
          $col += (2 * $topic_count);
        }
      }
      else {
        $sheet->setCellValue($this->getChar($col) . $row, $this->t('Student not subscribed'));
        // Increase columns to go to the end column.
        $col += (2 * $topic_count) + 1;
      }

      if ($is_timed_mode_test) {
        $time_per_q = '0 sec';
        if (isset($student_durations[$student['id']])) {
          $time_data = [
            'hr' => 0,
            'min' => 0,
            'sec' => $student_durations[$student['id']],
          ];
          $attempted = $student['data']['test_mode']['attempted'];
          $time_per_q = $this->getFormattedTimePerQuestion($time_data, $attempted);
        }
        $sheet->setCellValue($this->getChar($col) . $row, $time_per_q);
        $col++;
      }

      $row++;
    }

    if ($fill_topics) {
      // Check for class topic data.
      $topic_index = 1;
      foreach ($topics as $topic_id => $topic_result_data) {
        $row = 2;
        if ($topic_result_data['total_questions'] > 0) {
          $student_data = $this->qwreportingStudents->getStudents($group, $topic_id, NULL, NULL, $class_id, $qwiz_id, TRUE);
          foreach ($student_data as $student) {
            $col = ($topic_index * 2) + 2;
            $secondary_class = FALSE;
            if (!empty($student['data']['secondary'][$class_id]['qwiz_id'])) {
              $secondary_class = $student['data']['secondary'][$class_id];
            }
            elseif (!empty($student['data']['secondary'][$class_id][$topic_id]['qwiz_id'])) {
              $secondary_class = $student['data']['secondary'][$class_id][$topic_id];
            }
            elseif (!empty($student['data']['secondary'][$class_id][$qwiz_id])) {
              $secondary_class = $student['data']['secondary'][$class_id][$qwiz_id];
            }
            elseif (!empty($student['data']['secondary'][$class_id]) && isset($student['data']['secondary'][$class_id]['totalQuestion']) && $student['data']['secondary'][$class_id]['totalQuestion'] > 0) {
              $secondary_class = $student['data']['secondary'][$class_id];
            }
            elseif (empty($secondary_class) && !empty($student['data']['secondary'][$class_id]) && !isset($student['data']['secondary'][$class_id]['totalQuestion'])) {
              $secondary_class_array = $student['data']['secondary'][$class_id];
              $secondary_class = reset($secondary_class_array);
            }

            if (!empty($secondary_class)) {
              // Sometimes the totalQuestion label can come in different.
              // Account for both totalQuestion & total_questions.
              $q_label = 'totalQuestion';
              if (isset($secondary_class['total_questions'])) {
                $q_label = 'total_questions';
              }
              $topic_total = $secondary_class[$q_label];
              $topic_correct = $secondary_class['correct'];
              $topic_percent_raw = $topic_total ? ($topic_correct / $topic_total) * 100 : 0;
              $topic_percent = round($topic_percent_raw);
              $topic_out_of = '(' . $topic_correct . '/' . $topic_total . ')';
              $topic_data = $topic_percent . '% ' . $topic_out_of;
              $sheet->setCellValue($this->getChar($col) . $row, $topic_data);
              $col++;

              if (empty($secondary_class['avg'])) {
                $secondary_class['avg'] = 0;
              }
              $sheet->setCellValue($this->getChar($col) . $row, round($secondary_class['avg']) . '%');
            }
            else {
              $col++;
            }
            $row++;
          }
        }
        $topic_index++;
      }
    }

    $row = 2;
    foreach ($last_access_store as $last_access) {
      $sheet->setCellValue($this->getChar($col) . $row, $last_access);
      $row++;
    }

    // Autosize.
    foreach ($sheet->getColumnIterator() as $column) {
      $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(TRUE);
    }

    $writer = new Xlsx($spreadsheet);
    // Save it in a buffer.
    ob_start();
    $writer->save('php://output');
    $content = ob_get_clean();
    $response->setContent($content);
    return $response;
  }

  /**
   * Returns valid cell value.
   */
  protected function getChar($n) {
    $n++;
    $result = '';
    while ($n > 0) {
      $remainder = ($n - 1) % 26;
      $result = chr(65 + $remainder) . $result;
      $n = floor(($n - 1) / 26);
    }
    return $result;
  }

  /**
   * Returns formatted time.
   */
  protected function getFormattedTime($time_data) {
    // Extract hours, minutes, and seconds from the input.
    $hours = $time_data['hr'];
    $minutes = $time_data['min'];
    $seconds = $time_data['sec'];

    $hour = ($minutes > 240) ? '4+ HR' : $hours;
    $mins = ($seconds > 30) ? $minutes + 1 : $minutes;

    $formatted_time = '';
    if ($hour != 0) {
      $formatted_time = $hour . ' hr ' . $mins . ' min';
    }
    elseif ($mins == 0) {
      $formatted_time = $seconds . ' sec';
    }
    else {
      $formatted_time = $mins . ' min';
    }
    return $formatted_time;
  }

  /**
   * Get time spent per question.
   */
  protected function getFormattedTimePerQuestion($total_time, $ques_attempted) {
    $seconds = 0;
    $hr_seconds = $total_time['hr'] * 3600;
    $min_seconds = $total_time['min'] * 60;
    $seconds = $hr_seconds + $min_seconds + $total_time['sec'];

    $time_data = [
      'hr' => 0,
      'min' => 0,
      'sec' => 0,
    ];
    if ($ques_attempted != 0 && $seconds != 0) {
      $avg_time = $seconds / $ques_attempted;
      $time_data['sec'] = (int) round($avg_time);
    }
    return $this->getFormattedTime($time_data);
  }

}
