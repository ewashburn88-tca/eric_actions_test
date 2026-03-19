<?php

namespace Drupal\qwreporting\Controller;

use Dompdf\Exception;
use Drupal\Core\Controller\ControllerBase;
use Drupal\qwreporting\StudentsInterface;
use Drupal\qwreporting\GroupsInterface;
use Drupal\user\Entity\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates excel with details for student in the context of a group.
 */
class QwreportingExcelStudentDetailsController extends ControllerBase {

  /**
   * The qwreporting.students service.
   *
   * @var \Drupal\qwreporting\StudentsInterface
   */
  protected $qwreportingStudents;

  /**
   * The qwreporting.groups service.
   *
   * @var \Drupal\qwreporting\GroupsInterface
   */
  protected $qwreportingGroups;

  /**
   * QwreportingExcelStudentDetailsController constructor.
   *
   * @param \Drupal\qwreporting\StudentsInterface $qwreporting_students
   *   The qwreporting.students service.
   * @param \Drupal\qwreporting\GroupsInterface $qwreporting_groups
   *   The qwreporting.groups service.
   */
  public function __construct(StudentsInterface $qwreporting_students, GroupsInterface $qwreporting_groups) {
    $this->qwreportingStudents = $qwreporting_students;
    $this->qwreportingGroups = $qwreporting_groups;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('qwreporting.students'),
      $container->get('qwreporting.groups')
    );
  }

  /**
   * Builds the response.
   */
  public function build($course, $student) {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $student_user = User::load($student);
    if(empty($student_user)){
      echo 'Unable to load specified user with ID of '.$student; exit;
    }
    $filename = 'Qwiz Student Report '.$student;
    $response = new Response();
    $response->headers->set('Pragma', 'no-cache');
    $response->headers->set('Expires', '0');
    $response->headers->set('Content-Type', 'application/vnd.ms-excel');
    $response->headers->set('Content-Disposition', 'attachment; filename='.$filename.'.xlsx');
    $details = $this->qwreportingStudents->getStudentData($course, $student_user);
    #dpm($details); exit;
    if(empty($details)){
      echo 'Unable to load data for course '.$course.' fo user with ID of '.$student; exit;
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->getColumnDimension('A')->setAutoSize(TRUE);
    $sheet->getColumnDimension('B')->setAutoSize(TRUE);
    $sheet->getColumnDimension('C')->setAutoSize(TRUE);
    $sheet->setCellValue('A1', 'Individual Results of student: '.$student_user->getDisplayName());
    $sheet->getStyle('A1')->getFill()
      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('52d8f6');
    $sheet->setCellValue('B1', 'User ID '.$student_user->id());
    $sheet->getStyle('B1')->getFill()
      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('52d8f6');

    $i = 4;
    foreach (array_reverse($details['data']) as $class) {
      if(empty($class['classes'])) continue;
      if(empty($class['results'])) continue;

      // Class Header
      $sheet->setCellValue('A' . $i, $class['classes']['name']);
      foreach( range('A', 'F') as $letter) {
        $sheet->getStyle($letter.$i)->getFill()
          ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
          ->getStartColor()->setARGB('00ff00');
      }


      $i++;
      $sheet->setCellValue('A' . $i, $class['classes']['description']);
      $sheet->mergeCells('A' . $i . ':C' . $i);
      $i++;

      // Topic Headers
      $sheet->setCellValue('A' . $i, 'Topic Name');
      $sheet->setCellValue('B' . $i, 'Total');
      $sheet->setCellValue('C' . $i, 'Progress %');
      $sheet->setCellValue('D' . $i, 'Correct');
      $sheet->setCellValue('E' . $i, 'Attempted');
      $sheet->setCellValue('F' . $i, 'Score');

      // Style topic headers
      foreach( range('A', 'F') as $letter) {
        $sheet->getStyle($letter . $i)->getFont()->setBold(true);
      }
      $i++;

      // Topic Results
      foreach ($class['results'] as $result) {
        $sheet->setCellValue('A' . $i, $result['label']);
        $sheet->setCellValue('B' . $i, $result['total_questions']);
        if(empty($result['total_questions'])){
          $sheet->setCellValue('C' . $i, '0%');
        }else {
          $sheet->setCellValue('C' . $i, round(($result['correct'] / $result['total_questions']), 3) * 100 . '%');
        }
        $sheet->setCellValue('D' . $i, $result['actually_correct']);
        $sheet->setCellValue('E' . $i, $result['attempted']);
        $sheet->setCellValue('F' . $i, (round($result['score_attempted'], 3) * 100 . '%'));

        $i++;
      }
      $i++;
    }

    // Autosize
    foreach ($sheet->getColumnIterator() as $column) {
      $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
    }

    $writer = new Xlsx($spreadsheet);
    ob_start();
    $writer->save('php://output');
    $content = ob_get_clean();
    $response->setContent($content);

    return $response;
  }

}
