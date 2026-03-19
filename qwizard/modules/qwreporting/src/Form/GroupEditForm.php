<?php

namespace Drupal\qwreporting\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\taxonomy\TermInterface;
use Drupal\qwreporting\GroupsInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Quiz Wizard Reporting form.
 */
class GroupEditForm extends FormBase {

  private $groups;

  /**
   * Construct GroupEditForm.
   *
   * @param \Drupal\qwreporting\GroupsInterface @groups
   *   Object to inject.
   */
  public function __construct(GroupsInterface $groups) {
    $this->groups = $groups;
  }

  /**
   * Getting Group object from the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Inject Container.
   *
   * @return \Drupal\qwreporting\Form\GroupEditForm|mixed|object|null
   *   self with injected object.
   */
  public static function create(ContainerInterface $container) {
    $groups = $container->get('qwreporting.groups');
    return new static($groups);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'qwreporting_group_edit';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $group = NULL) {
    // Sometimes AJAX is weird about $this->groups being unavailable
    if(empty($this->groups)){
      $this->groups = \Drupal::service('qwreporting.groups');
    }

    $form = [];
    $data = NULL;
    $courses = $this->groups->getCourses();
    $group_admins = [];
    $current_user = \Drupal::currentUser();
    // @todo D10
    //   $user_admin = \Drupal::service('permission_checker')->hasPermission('administer users', $current_user);
    $user_admin = $current_user->hasPermission('administer users');

    if (!empty($group)) {
      $data = $this->groups->getGroup($group);
      $data_tid = $data->tid->getValue()[0]['value'];

      $form['id'] = [
        "#type" => 'hidden',
        '#value' => $data_tid,
      ];
    }
    $group_admins = $this->getAdmins($data, $form_state);
    $group_students = $this->getStudents($data, $form_state);

    $group_links = [];

    if (!empty($group)) {
      // View Group.
      $view_link = Url::fromRoute('qwreporting.results.individual', ['group' => $group]);
      $group_links[] = Link::fromTextAndUrl($this->t('View Group'), $view_link)->toString();
      if ($user_admin) {
        $maint_link = Url::fromRoute('qwmaintenance.qw_maintenance_form', [
          'group_id' => $group,
          'destination' => \Drupal::service('path.current')->getPath(),
        ]);
        $group_links[] = Link::fromTextAndUrl($this->t('Group User Maint'), $maint_link)
          ->toString();

        $import_link = Url::fromRoute('qwreporting.import_users', [
          'group_id' => $group,
        ]);
        $group_links[] = Link::fromTextAndUrl($this->t('Import Students'), $import_link)
          ->toString();
      }
      // Email link.
      $email_link = Url::fromRoute('qwreporting.group_email', [
        'group_id' => $group,
      ],
      [
        'query' => $this->getDestinationArray(),
      ]);
      $group_links[] = Link::fromTextAndUrl($this->t('Send Email'), $email_link)->toString();
    }

    if (!empty($group_links)) {
      $form['group_links'] = [
        '#type' => 'markup',
        '#markup' => implode('&nbsp;&nbsp;|&nbsp;&nbsp;', $group_links),
      ];
    }
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Group name'),
      '#required' => TRUE,
      '#default_value' => ($data != NULL) ? $data->getName() : "",
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#required' => FALSE,
      '#default_value' => ($data != NULL) ? $data->getDescription() : "",
    ];


    if (empty($group)) {
      $form['administratorswarning'] = [
        '#type' => 'markup',
        '#markup' => '<p>Assign administrators to this course after initial creation</p>',
      ];

      $form['studentswarning'] = [
        '#type' => 'markup',
        '#markup' => '<p>Assign students to this course after initial creation</p>',
      ];
    }else{
      // Default user form item
      $user_form_item = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        '#description' => '',
        '#tags' => TRUE,
        '#title' => '',
        '#multiple' => false,
        '#required' => FALSE,
        '#selection_handler' => 'default',
      ];

      // Admins
      $form['administrators'] = [
        '#type' => 'fieldset',
        '#title' => 'Administrators',
        '#prefix' => '<div id="administrators-fieldset-wrapper">',
        '#suffix' => '</div>',
      ];
      $form['administrators']['actions'] = [
        '#type' => 'actions',
      ];
      $form['administrators']['actions']['add_name'] = [
        '#type' => 'submit',
        '#value' => 'Add Admin',
        '#submit' => ['::addOneAdmin'],
        '#ajax' => [
          'callback' => '::addmoreAdminsCallback',
          'wrapper' => 'administrators-fieldset-wrapper',
        ],
      ];


      $num_admins = $form_state->get('num_admins');
      $form_state->set('num_admins', $num_admins);
      $i = 0;
      if ($num_admins === NULL) {
        // Defaults on load
        $num_admins = count($group_admins);
        $form_state->set('num_admins', $num_admins);


        while ($i < count($group_admins)) {
          $form['administrators']['values']['admins_' . $i] = array_merge($user_form_item, ['#default_value' => $group_admins[$i]]);
          $i++;
        }
      }

      while ($i < $num_admins + 1) {
        $form['administrators']['values']['admins_' . $i] = array_merge($user_form_item, []);
        $i++;
      }


      // Students
      $form['students'] = [
        '#type' => 'fieldset',
        '#title' => 'Students',
        '#prefix' => '<div id="students-fieldset-wrapper">',
        '#suffix' => '</div>',
      ];
      $form['students']['actions'] = [
        '#type' => 'actions',
      ];
      $form['students']['actions']['add_name'] = [
        '#type' => 'submit',
        '#value' => 'Add Student',
        '#submit' => ['::addOneStudent'],
        '#ajax' => [
          'callback' => '::addmoreStudentsCallback',
          'wrapper' => 'students-fieldset-wrapper',
        ],
      ];

      $num_students = $form_state->get('num_students');
      $form_state->set('num_students', $num_students);
      $i = 0;
      if ($num_students === NULL) {
        // Defaults on load
        $num_students = count($group_students);
        $form_state->set('num_students', $num_students);


        while ($i < count($group_students)) {
          $form['students']['values']['students_' . $i] = array_merge($user_form_item, ['#default_value' => $group_students[$i]]);
          $i++;
        }
      }

      while ($i < $num_students + 1) {
        $form['students']['values']['students_' . $i] = array_merge($user_form_item, []);
        $i++;
      }
    }


    $form['course'] = [
      '#type' => 'select',
      '#title' => t('Course'),
      '#default_value' => ($data != NULL) ? $data->field_course->target_id : "",
      '#required' => FALSE,
      '#options' => $courses,
    ];

    $archived_data = NULL;
    if (!empty($data) && $data instanceof TermInterface) {
      $archived_data = $data->get('field_archived')->getValue();
    }

    $form['archived'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Archived'),
      '#default_value' => !empty($archived_data[0]['value']),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form;
  }

  // Admin Ajax
  public function addOneAdmin(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_admins');
    $add_button = $name_field + 1;
    $form_state->set('num_admins', $add_button);
    $form_state->setRebuild();
  }
  public function addmoreAdminsCallback(array &$form, FormStateInterface $form_state) {
    return $form['administrators'];
  }
  public function getAdmins($group, FormStateInterface $form_state){
    // @todo this is empty on the add version of this form. No clue why
    $values = $form_state->getValues();
    #var_dump($values); exit;
    $admins = [];

    // Try form_state first
    foreach($values as $key=>$value){
      if(str_starts_with($key, 'admins_') && !empty($value[0]['target_id'])){
        $admins[] = User::load($value[0]['target_id']);
      }
    }


    // Else get default
    if(empty($admins) && !empty($group)){
      foreach($group->field_administrator->getValue() as $uid){
        $uid = $uid['target_id'];
        $admins[] = User::load($uid);
      }
    }

    return $admins;
  }

  // Student Ajax
  public function addOneStudent(array &$form, FormStateInterface $form_state) {
    $name_field = $form_state->get('num_students');
    $add_button = $name_field + 1;
    $form_state->set('num_students', $add_button);
    $form_state->setRebuild();
  }
  public function addmoreStudentsCallback(array &$form, FormStateInterface $form_state) {
    return $form['students'];
  }
  public function getStudents($group, FormStateInterface $form_state){
    // @todo this is empty on the add version of this form. No clue why
    $values = $form_state->getValues();
    #var_dump($values); exit;
    $students = [];

    // Try form_state first
    foreach($values as $key=>$value){
      if (str_starts_with($key, 'students_') && !empty($value[0]['target_id'])) {
        $students[] = User::load($value[0]['target_id']);
      }
    }

    // Else get default
    if(empty($students) && !empty($group)){
      foreach($group->field_students->getValue() as $uid){
        $uid = $uid['target_id'];
        $students[] = User::load($uid);
      }
    }

    return $students;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (mb_strlen($form_state->getValue('name')) < 5) {
      $form_state->setErrorByName('name', $this->t('Message should be at least 5 characters.'));
    }
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Sometimes AJAX is weird about $this->groups being unavailable
    if(empty($this->groups)){
      $this->groups = \Drupal::service('qwreporting.groups');
    }

    $values = $form_state->getValues();
    $students = [];
    foreach($values as $key=>$value){
      if(str_starts_with($key, 'students_')){
        if(!empty($value[0]['target_id'])) {
          $students[] = $value[0];
        }
      }
    }
    $admins = [];
    foreach($values as $key=>$value){
      if(str_starts_with($key, 'admins_')){
        if(!empty($value[0]['target_id'])) {
          $admins[] = $value[0];
        }
      }
    }

    if(!empty($form_state->getValue('id'))) {
      // Edit
      $result = $this->groups->editGroup($form_state->getValue('id'), [
        "name" => $form_state->getValue('name'),
        "description" => $form_state->getValue('description'),
        "students" => $students,
        "administrators" => $admins,
        "course" => $form_state->getValue('course'),
        "archived" => $form_state->getValue('archived'),
      ]);
    }else{
      // Add
      $result = $this->groups->createGroup([
        "name" => $form_state->getValue('name'),
        "description" => $form_state->getValue('description'),
        "students" => [],
        "administrators" => [],
        "course" => $form_state->getValue('course'),
        "archived" => $form_state->getValue('archived'),
      ]);
    }


    if ($result) {
      try {
        $this->messenger()->addStatus($this->t('The group was saved'));
        $form_state->setRedirect('qwreporting.group_edit', ['group' => $result->id()]);
      }catch(\Exception $e){
        \Drupal::logger('qwreports')->error($e->getMessage().' | '.$e->getTraceAsString());
        \Drupal::messenger()->addError($e->getMessage());
      }
    }
  }
}
