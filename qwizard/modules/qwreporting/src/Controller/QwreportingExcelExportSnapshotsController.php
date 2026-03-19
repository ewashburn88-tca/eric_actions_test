<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\qwreporting\GroupsInterface;
use Drupal\qwreporting\StudentsInterface;
use Drupal\qwsubs\SubscriptionHandlerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Route callback for export snapshot results.
 */
class QwreportingExcelExportSnapshotsController extends ControllerBase {

  /**
   * The kill switch.
   */
  protected KillSwitch $killSwitch;

  /**
   * The date formatter.
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The qwreporting.students service.
   */
  protected StudentsInterface $qwreportingStudents;

  /**
   * The qwreporting.groups service.
   */
  protected GroupsInterface $qwreportingGroups;

  /**
   * The subscription handler.
   */
  protected SubscriptionHandlerInterface $subscriptionHandler;

  /**
   * QwreportingExcelExportSnapshotsController constructor.
   *
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $kill_switch
   *   The kill switch.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\qwreporting\StudentsInterface $qwreporting_students
   *   The qwreporting.students service.
   * @param \Drupal\qwreporting\GroupsInterface $qwreporting_groups
   *   The qwreporting.groups service.
   * @param \Drupal\qwsubs\SubscriptionHandlerInterface $subscription_handler
   *   The subscription handler.
   */
  public function __construct(KillSwitch $kill_switch, DateFormatterInterface $date_formatter, StudentsInterface $qwreporting_students, GroupsInterface $qwreporting_groups, SubscriptionHandlerInterface $subscription_handler) {
    $this->killSwitch = $kill_switch;
    $this->dateFormatter = $date_formatter;
    $this->qwreportingStudents = $qwreporting_students;
    $this->qwreportingGroups = $qwreporting_groups;
    $this->subscriptionHandler = $subscription_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('page_cache_kill_switch'),
      $container->get('date.formatter'),
      $container->get('qwreporting.students'),
      $container->get('qwreporting.groups'),
      $container->get('qwsubs.subscription_handler')
    );
  }

  /**
   * Builds the response as buffer.
   */
  public function build($group, $exam_id) {
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
    // Load the course.
    $course = $this->entityTypeManager()->getStorage('taxonomy_term')->load($course_id);

    $stored_students = [];
    // Get students.
    $students = $group_term->get('field_students')->referencedEntities();
    $student_sessions = [];
    foreach ($students as $student) {
      $student_uid = $student->id();
      $stored_students[$student_uid] = $student;
      $subscription = $this->subscriptionHandler->getCurrentSubscription($course, $student_uid, NULL, TRUE);
      if (empty($subscription)) {
        continue;
      }
      $details = $this->qwreportingStudents->getStudentData($course_id, $student, TRUE, NULL, NULL, TRUE);
      // @todo Do this for readiness exams only.
      // Prepare the session data.
      if (!empty($details['data'])) {
        $data = $details['data'];
        foreach ($data as $resultset) {
          $class_id = $resultset['class'];
          if ($class_id != $exam_id) {
            // Only do this for selected exam.
            continue;
          }
          if (!empty($resultset['results'])) {
            foreach ($resultset['results'] as $result) {
              if (!empty($result['snapshots'])) {
                $student_snapshots = $result['snapshots'];
                foreach ($student_snapshots as $snapshot) {
                  $student_sessions[$student_uid][] = [
                    'uid' => $student_uid,
                    'class_id' => $class_id,
                    'result_label' => $result['label'],
                    'snapshot' => $snapshot,
                  ];
                }
              }
            }
          }
        }
      }
    }

    $loaded_exam = $this->entityTypeManager()->getStorage('taxonomy_term')->load($exam_id);

    $filename = $this->t('Student Test Results @group_id - @exam', [
      '@group_id' => $group,
      '@exam' => $loaded_exam->getName(),
    ]);

    $same_data = [];
    $data_rows = [];
    $total_test_items = [];
    foreach ($student_sessions as $uid => $snapshot_data) {
      $row_data = [];
      $student = $stored_students[$uid];
      // Student name.
      if (empty($same_data['combined_names'][$uid])) {
        // Combine first & last name.
        $combined_name = [];
        foreach (['field_last_name', 'field_first_name'] as $field_name) {
          $name = $student->get($field_name)->getString();
          if (!empty($name)) {
            $combined_name[] = $name;
          }
        }
        $same_data['combined_names'][$uid] = implode(', ', $combined_name);
      }

      $total_time = [
        'hr' => 0,
        'min' => 0,
        'sec' => 0,
      ];
      $row_data = [
        'name' => $same_data['combined_names'][$uid],
        'total_time_spent' => '',
        'total_score_correct' => 0,
        'total_attempted' => 0,
        'time_per_q' => 0,
      ];
      $total_ques_attempted = 0;
      $n = 0;
      $total_tests = 0;
      foreach ($snapshot_data as $session_data) {
        $i = $n + 1;
        $class_id = $session_data['class_id'];
        $snapshot = $session_data['snapshot'];
        // Total time.
        $elapsed_sec = (int) $snapshot['elapsed_sec'];
        $elapsed_min = (int) $snapshot['elapsed_min'];
        $elapsed_hr = (int) $snapshot['elapsed_hour'];
        $total_time['hr'] += $elapsed_hr;
        $total_time['min'] += $elapsed_min;
        $total_time['sec'] += $elapsed_sec;

        $attempted_ques = (int) $snapshot['attempted'];
        $total_ques_attempted += $attempted_ques;

        // Total score.
        $row_data['total_score_correct'] += (int) $snapshot['correct'];
        $row_data['total_attempted'] += $attempted_ques;

        // Score.
        $row_data['score_attempted_' . $i] = $snapshot['score_attempted'];
        $row_data['score_correct_' . $i] = $snapshot['correct'];
        $row_data['attempted_' . $i] = $attempted_ques;

        // Created.
        $row_data['created_' . $i] = $this->dateFormatter->format($snapshot['created'], 'custom', 'm-d-Y');

        // Time spent for test.
        $elapsed_time_for_test = [
          'hr' => $elapsed_hr,
          'min' => $elapsed_min,
          'sec' => $elapsed_sec,
        ];
        $hour = ($elapsed_min > 240) ? '4+ HR' : $elapsed_hr;
        $mins = ($elapsed_sec > 30) ? $elapsed_min + 1 : $elapsed_min;
        $elapsed_time = '';
        if ($hour != 0) {
          $elapsed_time = $hour . ' hr ' . $mins . ' min';
        }
        elseif ($mins == 0) {
          $elapsed_time = $elapsed_sec . ' sec';
        }
        else {
          $elapsed_time = $mins . ' min';
        }
        $row_data['time_spent_' . $i] = $elapsed_time;

        // Time per question of this test.
        $row_data['time_per_q_' . $i] = $this->getFormattedTimePerQuestion($elapsed_time_for_test, $attempted_ques);

        $n++;
      }

      if ($total_tests < $n) {
        $total_tests = $n;
      }
      $total_test_items[] = $total_tests;

      $row_data['total_time_spent'] = $this->getFormattedTime($total_time);
      $row_data['time_per_q'] = $this->getFormattedTimePerQuestion($total_time, $total_ques_attempted);

      $row_data['total_attempted'] = $total_ques_attempted;

      $data_rows[] = $row_data;
    }

    // Prepare response object.
    $response = new Response();
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment; filename=' . $filename . '.xlsx');

    // Create an excel.
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', $this->t('Test sessions of group: @name for @exam', [
      '@name' => $group_data['name'],
      '@exam' => $loaded_exam->getName(),
    ]));
    $sheet->getStyle('A1')->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()->setARGB('52d8f6');

    $col = 0;
    $sheet->setCellValue($this->getChar($col) . '2', $this->t('Student Name'));
    $sheet->getStyle($this->getChar($col) . '2')
      ->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setARGB('00ff00');
    $col++;
    $sheet->setCellValue($this->getChar($col) . '2', $this->t('Total time'));
    $sheet->getStyle($this->getChar($col) . '2')
      ->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setARGB('00ff00');
    $col++;
    $sheet->setCellValue($this->getChar($col) . '2', $this->t('Time per Q - ALL'));
    $sheet->getStyle($this->getChar($col) . '2')
      ->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setARGB('00ff00');
    $col++;
    $sheet->setCellValue($this->getChar($col) . '2', $this->t('Score ALL'));
    $sheet->getStyle($this->getChar($col) . '2')
      ->getFill()
      ->setFillType(Fill::FILL_SOLID)
      ->getStartColor()
      ->setARGB('00ff00');
    $col++;

    $header_items = max($total_test_items);
    // Based on total number of tests, prepare the header.
    for ($c = 1; $c <= $header_items; $c++) {
      $sheet->setCellValue($this->getChar($col) . '2', $this->t('Date test @n', [
        '@n' => $c,
      ]));
      $sheet->getStyle($this->getChar($col) . '2')
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('00ff00');
      $col++;
      $sheet->setCellValue($this->getChar($col) . '2', $this->t('Time test @n', [
        '@n' => $c,
      ]));
      $sheet->getStyle($this->getChar($col) . '2')
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('00ff00');
      $col++;
      $sheet->setCellValue($this->getChar($col) . '2', $this->t('Time per Q - @n', [
        '@n' => $c,
      ]));
      $sheet->getStyle($this->getChar($col) . '2')
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('00ff00');
      $col++;
      $sheet->setCellValue($this->getChar($col) . '2', $this->t('Score @n', [
        '@n' => $c,
      ]));
      $sheet->getStyle($this->getChar($col) . '2')
        ->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()
        ->setARGB('00ff00');
      $col++;
    }

    // Merge first header row.
    $sheet->mergeCells('A1:' . $this->getChar($col - 1) . '1');

    // Start with 3rd row since we have filled up first 2 rows.
    $row = 3;
    foreach ($data_rows as $item) {
      $item_count = (count($item) - 4) / 3;

      $col = 0;
      $sheet->setCellValue($this->getChar($col) . $row, $item['name']);
      $col++;

      $sheet->setCellValue($this->getChar($col) . $row, $item['total_time_spent']);
      $col++;

      $sheet->setCellValue($this->getChar($col) . $row, $item['time_per_q']);
      $col++;

      $total_score_percentage = ($item['total_score_correct'] / $item['total_attempted']) * 100;
      $total_score = '';
      $total_score .= number_format($total_score_percentage, 2);
      $total_score .= '% (' . $item['total_score_correct'] . ' / ' . $item['total_attempted'] . ')';
      $sheet->setCellValue($this->getChar($col) . $row, $total_score);
      $col++;

      for ($i = 1; $i <= $item_count; $i++) {
        $keys = ['created_', 'time_spent_', 'time_per_q_'];
        foreach ($keys as $key) {
          if (isset($item[$key . $i])) {
            $sheet->setCellValue($this->getChar($col) . $row, $item[$key . $i]);
          }
          $col++;
        }
        // Add score.
        if (isset($item['score_attempted_' . $i]) && $item['score_correct_' . $i] && $item['attempted_' . $i]) {
          $score = '';
          $score .= number_format($item['score_attempted_' . $i], 2) * 100;
          $score .= '% (' . $item['score_correct_' . $i] . ' / ' . $item['attempted_' . $i] . ')';
          $sheet->setCellValue($this->getChar($col) . $row, $score);
        }
        $col++;
      }
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
