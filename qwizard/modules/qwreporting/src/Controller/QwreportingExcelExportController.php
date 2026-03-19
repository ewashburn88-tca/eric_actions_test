<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Quiz Wizard Reporting routes.
 */
class QwreportingExcelExportController extends ControllerBase {

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
   * QwreportingExcelExportController constructor.
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
   */
  public function __construct(Connection $database, RequestStack $request_stack, KillSwitch $kill_switch, QwizardGeneralInterface $qw_general_manager, StudentsInterface $qwreporting_students, GroupsInterface $qwreporting_groups) {
    $this->database = $database;
    $this->requestStack = $request_stack;
    $this->killSwitch = $kill_switch;
    $this->qwGeneralManager = $qw_general_manager;
    $this->qwreportingStudents = $qwreporting_students;
    $this->qwreportingGroups = $qwreporting_groups;
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
      $container->get('qwreporting.groups')
    );
  }

  /**
   * Builds the response as buffer.
   */
  public function build($group) {
    // Trigger the page cache kill switch.
    $this->killSwitch->trigger();

    $taxonomy_storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $group_term = $taxonomy_storage->load($group);
    if (empty($group_term)) {
      $message = "Unable to load specified group with ID of $group";
      throw new NotFoundHttpException($message);
    }

    // Get the groups.
    $groups = $this->qwreportingGroups->getGroups();
    if (empty($groups[$group])) {
      $message = "Unable to find specified group with ID of $group";
      throw new NotFoundHttpException($message);
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

    $query = $this->requestStack->getCurrentRequest()->query->all();
    $selected_topic_string = $query['topic'] ?? NULL;
    $selected_qwiz_id = NULL;
    $selected_topic_id = NULL;
    $selected_class_id = NULL;
    $is_primary_course = TRUE;
    if (!empty($selected_topic_string)) {
      $selected_info = $this->qwGeneralManager->getQwizInfoFromTagString($selected_topic_string);
      if (!empty($selected_info)) {
        $selected_class_id = $selected_info['class'];
        $selected_topic_id = $selected_info['topic'];
        $selected_qwiz_id = $selected_info['qwiz'];
        $is_primary_course = $selected_info['is_primary_course'];
      }
    }

    $period = $query['period'] ?? NULL;
    $period_days_start = $query['date_start'] ?? NULL;
    $period_end = $query['date_end'] ?? NULL;
    $date_filter_option = $query['filter_option'] ?? NULL;

    if ($date_filter_option == 'by_period') {
      $period_days_start = NULL;
      $period_end = NULL;
    }
    elseif ($date_filter_option == 'by_dates') {
      $period = $period_days_start;
    }

    $group_details = $this->qwreportingGroups->getGroupData($group);

    $since_timestamp = NULL;
    if (!empty($period)) {
      $since_timestamp = strtotime($period);
    }
    $since_end_timestamp = NULL;
    if (!empty($period_end)) {
      $since_end_timestamp = strtotime($period_end);
      // Add a day to get end of the day.
      $since_end_timestamp = $since_end_timestamp + 86400;
    }

    $students = $this->qwreportingStudents->getStudents($group, $selected_topic_id, $since_timestamp, $since_end_timestamp, $selected_class_id, $selected_qwiz_id, TRUE);
    $group_details = $this->qwreportingGroups->getGroupData($group);

    // Set header & also apply colors.
    $sheet->setCellValue('A1', $this->t('Student Name'));
    $sheet->setCellValue('B1', $this->t('Email'));
    $this->applyColorToCell($sheet, 'A1', 'efefef');
    $this->applyColorToCell($sheet, 'B1', 'efefef');

    if (!$is_primary_course) {
      $sheet->setCellValue('C1', $this->t('Overall Total'));
      $sheet->setCellValue('D1', $this->t('Overall Correct'));
      $sheet->setCellValue('E1', $this->t('Overall Progress %'));
      $sheet->setCellValue('F1', $this->t('Score'));
      $sheet->setCellValue('G1', $this->t('Last Access Time'));
      foreach (range('C', 'E') as $letter) {
        $this->applyColorToCell($sheet, $letter . '1', 'd5a6bd');
      }
      $this->applyColorToCell($sheet, 'F1', 'efefef');
      $this->applyColorToCell($sheet, 'G1', 'efefef');
    }
    else {
      $sheet->setCellValue('C1', $this->t('Overall Total #Qs'));
      $sheet->setCellValue('D1', $this->t('Overall #Qs Correct'));
      $sheet->setCellValue('E1', $this->t('Overall Progress %'));
      foreach (range('C', 'E') as $letter) {
        $this->applyColorToCell($sheet, $letter . '1', 'd5a6bd');
      }

      $sheet->setCellValue('F1', $this->t('Total # Practice Qs'));
      $sheet->setCellValue('G1', $this->t('# Practice Qs Correct'));
      $sheet->setCellValue('H1', $this->t('Progress (% Practice Qs correct)'));
      $sheet->setCellValue('I1', $this->t('# Practice Qs Attempted'));
      $sheet->setCellValue('J1', $this->t('Avg Score Practice Q Tests'));
      foreach (range('F', 'J') as $letter) {
        $this->applyColorToCell($sheet, $letter . '1', '23dc00');
      }

      $sheet->setCellValue('K1', $this->t('Total # TIMED Qs'));
      $sheet->setCellValue('L1', $this->t('# TIMED Qs Correct'));
      $sheet->setCellValue('M1', $this->t('Progress (% TIMED Qs correct)'));
      $sheet->setCellValue('N1', $this->t('# TIMED Qs Attempted'));
      $sheet->setCellValue('O1', $this->t('Avg Score TIMED Q Tests'));
      $sheet->setCellValue('P1', $this->t('Avg Duration per TIMED Q'));
      foreach (range('K', 'P') as $letter) {
        $this->applyColorToCell($sheet, $letter . '1', '6fa8dc');
      }

      $sheet->setCellValue('Q1', $this->t('Last Access Time'));
      $this->applyColorToCell($sheet, 'Q1', 'efefef');
    }

    // Fetch total test mode duration in seconds for each student natively in SQL.
    $student_durations = [];
    if (!empty($students) && !empty($is_primary_course)) {
      $student_ids = array_column($students, 'id');
      if (!empty($student_ids)) {
        $test_mode_classes = $this->qwGeneralManager->getStatics('test_mode_classes');

        $duration_query = $this->database->select('qwiz_result', 'qr');
        $duration_query->condition('qr.user_id', $student_ids, 'IN');
        $duration_query->condition('qr.course', $group_details['course']);
        $duration_query->condition('qr.class', $test_mode_classes, 'IN');
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

    // We will center all columns.
    $sheet->getStyle('A:P')
      ->getAlignment()
      ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $index = 2;
    foreach ($students as $student) {
      $combined = ['overall' => 0, 'correct' => 0, 'total' => 0];
      if (empty($is_primary_course)) {
        $secondary_tests = $student['data']['secondary'];
        $test = NULL;
        if (!empty($secondary_tests[$selected_class_id][$selected_qwiz_id])) {
          $test = $secondary_tests[$selected_class_id][$selected_qwiz_id];
        }
        elseif (!empty($secondary_tests[$selected_class_id][$selected_topic_id])) {
          $test = $secondary_tests[$selected_class_id][$selected_topic_id];
        }
        elseif (is_array($secondary_tests[$selected_class_id])) {
          $test = reset($secondary_tests[$selected_class_id]);
        }

        if (!empty($test)) {
          if (!empty($test['totalQuestion'])) {
            $combined = [
              'overall' => NULL,
              'correct' => $test['correct'],
              'total' => $test['totalQuestion'],
              'avg' => $test['avg'],
            ];
          }
          else {
            $combined = [
              'overall' => NULL,
              'correct' => $test['correct'],
              'total' => $test['total_questions'],
              'avg' => $test['avg'],
            ];
          }
        }
      }
      else {
        if (!empty($student['data']['overallProgress'])) {
          $combined['overall'] += (float) trim($student['data']['overallProgress'], '%');
        }
        if (!empty($student['data']['test_mode']['overallProgress'])) {
          $combined['overall'] += (float) trim($student['data']['test_mode']['overallProgress'], '%');
        }

        if (!empty($student['data']['correct'])) {
          $combined['correct'] += $student['data']['correct'];
        }
        if (!empty($student['data']['test_mode']['correct'])) {
          $combined['correct'] += $student['data']['test_mode']['correct'];
        }

        if (!empty($student['data']['totalQuestion'])) {
          $combined['total'] += $student['data']['totalQuestion'];
        }
        if (!empty($student['data']['test_mode']['totalQuestion'])) {
          $combined['total'] += $student['data']['test_mode']['totalQuestion'];
        }
      }

      $sheet->setCellValue('A' . $index, $student['combined_name']);
      $sheet->setCellValue('B' . $index, $student['email']);

      // Totals.
      $sheet->setCellValue('C' . $index, $combined['total']);
      $sheet->setCellValue('D' . $index, $combined['correct']);

      if (empty($combined['total'])) {
        $sheet->setCellValue('E' . $index, '0%');
      }
      else {
        $sheet->setCellValue('E' . $index, round(($combined['correct'] / $combined['total']), 2) * 100 . '%');
      }

      if (!empty($is_primary_course)) {
        // Study Mode - Practice Tests.
        $sheet->setCellValue('F' . $index, $student['data']['totalQuestion']);
        $sheet->setCellValue('G' . $index, $student['data']['correct']);
        $student['data']['overallProgress'] = (int) str_replace('%', '', $student['data']['overallProgress']);
        $sheet->setCellValue('H' . $index, round($student['data']['overallProgress'], 1) . '%');
        $sheet->setCellValue('I' . $index, $student['data']['attempted']);
        $sheet->setCellValue('J' . $index, round($student['data']['avg'], 0) . '%');

        // @todo fix undefined index errors for totalQuestion, correct,
        // overallProgress, attempted & avg in below code.
        // Test Mode - Timed Tests.
        $sheet->setCellValue('K' . $index, $student['data']['test_mode']['totalQuestion']);
        $sheet->setCellValue('L' . $index, $student['data']['test_mode']['correct']);
        $student['data']['test_mode']['overallProgress'] = (int) str_replace('%', '', $student['data']['test_mode']['overallProgress']);
        $sheet->setCellValue('M' . $index, round($student['data']['test_mode']['overallProgress'], 1) . '%');
        $sheet->setCellValue('N' . $index, $student['data']['test_mode']['attempted']);
        $sheet->setCellValue('O' . $index, round($student['data']['test_mode']['avg'], 1) . '%');

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
        $sheet->setCellValue('P' . $index, $time_per_q);

        $sheet->setCellValue('Q' . $index, $student['last_access'] ? date('m/d/Y H:i', $student['last_access']) : 'never');
      }
      else {
        if (!empty($combined['avg'])) {
          $sheet->setCellValue('F' . $index, round($combined['avg'], 1) . '%');
        }
        $sheet->setCellValue('G' . $index, $student['last_access'] ? date('m/d/Y H:i', $student['last_access']) : 'never');
      }

      $index++;
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
   * Apply color to specified cell.
   */
  protected function applyColorToCell(&$sheet, $cell, $color) {
    $sheet->getStyle($cell)->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setARGB($color);
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
