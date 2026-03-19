<?php

namespace Drupal\qwreporting;

use Dompdf\Exception;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\qwizard\QwizardGeneral;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Access for Groups.
 */
class QwreportingGroups implements GroupsInterface {

  use StringTranslationTrait;

  /**
   * Tag name for role for easy change.
   *
   * @var string
   */
  protected $vid = 'associations';
  protected $studentHandler;
  protected $user;

  /**
   * @param QwreportingStudents|null $studentHandler
   */
  public function __construct(QwreportingStudents $studentHandler) {
    $this->studentHandler = $studentHandler;
  }


  /**
   * @param ContainerInterface $container
   * @return QwreportingGroups
   * @throws \Psr\Container\ContainerExceptionInterface
   * @throws \Psr\Container\NotFoundExceptionInterface
   */
  public static function create(ContainerInterface $container): QwreportingGroups
  {
    return new static(
      $container->get('qwreporting.students')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getGroups():array {
    $group = [];
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $this->vid);
    $user_roles = User::load(\Drupal::currentUser()->id())->getRoles();

    // Add it to allow superuser to see all. Restrict access otherwise
    if (!in_array('administrator', $user_roles) && !in_array('manager', $user_roles)) {
      $query->condition('field_administrator', \Drupal::currentUser()->id());
    }
    $tids = $query->execute();
    if (count($tids) > 0) {
      $taxonomies = Term::loadMultiple($tids);
      foreach ($taxonomies as $taxonomy) {
        $group[$taxonomy->id()] = [
          'id' => $taxonomy->id(),
          'name' => $taxonomy->getName(),
          'description' => $taxonomy->getDescription(),
          'archived' => $taxonomy->get('field_archived')->getString(),
        ];
      }
    }

    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function createGroup($array) {
    if (!$this->existsGroupName($array['name'])) {
      $default_admin = \Drupal::currentUser()->id();
      $term = Term::create([
        'vid' => $this->vid,
        'field_administrator' => $default_admin,
        'field_course' => $array['course'],
        'name' => $array['name'],
        'description' => $array['description'],
        'field_students' => $array['students'],
        'field_archive' => $array['archived'],
      ]);
      $term->save();
      return $term;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public function editGroup($id, $array) {
    $term = Term::load($id);
    if ($term) {
      $term->setDescription($array['description']);
      $term->setName($array['name']);
      $term->field_students->setValue($array['students']);
      $term->field_administrator->setValue($array['administrators']);
      $term->field_course->setValue($array['course']);
      $term->field_archived->setValue($array['archived']);
      $term->save();
      return $term;
    }
    return FALSE;
  }

  /**
   * Returns a school ID from faculty profile for a user ID. Will be based off current user if $uid is null
   *
   * @param $uid
   * @return int
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFacultySchoolForUser($uid = null): int{
    $user = QwizardGeneral::getAccountInterface($uid);
    $profile = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser($user, 'faculty');

    if(empty($profile)) Throw new \Exception('Unable to load your faculty profile. Set it at /user/'.$user->id().'/faculty');
    if(empty($profile->get('field_school')->getValue())) Throw new \Exception('Unable to read school information from your faculty profile. Set it at /user/'.$user->id().'/faculty');

    $school_id = $profile->get('field_school')->getValue()[0]['target_id'];
    if(empty($school_id)) $school_id = 0;

    return $school_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupData($group):array {
    $group = $this->getGroup($group);
    return [
      "id" => $group->id(),
      "name" => $group->getName(),
      "description" => $group->getDescription(),
      "course" => $group->field_course->target_id,
      "topics" => $this->getCourseTopics($group->field_course->target_id, true),
      "main_categories" => $this->getMainCategories($group->field_course->target_id),
    ];
  }

  function getMainCategories($course_id){
    $topic_names = [];
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $statics = $QwizardGeneral->getStatics();
    $multiquiz_quizzes = $statics['multiquiz_quizzes'];
    $banlist_topics = $statics['banlist_topics'];
    $study_test_classes = $statics['study_test_classes'];
    $study_classes = $statics['study_classes'];
    $test_classes = $statics['test_class_ids'];

    $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $current_qwiz = $qwiz_storage->load($multiquiz_quizzes[$course_id]);
    $qwiz_topics = $current_qwiz->get('topics');
    $topic_ids = [];
    foreach ($qwiz_topics as $topic) {
      $topic_id = $topic->getValue()['target_id'];
      if (in_array($topic_id, $banlist_topics)) {
        continue;
      }
      $topic_ids[] = $topic_id;
    }

    $topic_terms = $taxonomy_storage->loadMultiple($topic_ids);
    foreach($topic_terms as $term) {
      $topic_names[$term->id()] = $term->label();
    }

    $quiz_options = _get_specified_topic_options(true);
    $options_by_class = [];
    foreach($quiz_options as $selected_topic_string=>$label){
      if(!str_starts_with($selected_topic_string, $course_id)){
        unset($quiz_options[$selected_topic_string]);
        continue;
      }

      $selected_info = \Drupal::service('qwizard.general')->getQwizInfoFromTagString($selected_topic_string);

      if (!empty($selected_info)) {
        $selected_class_id = $selected_info['class'];
        $selected_topic_id = $selected_info['topic'];
        $selected_qwiz_id = $selected_info['qwiz'];
        $is_primary_course = $selected_info['is_primary_course'];

        // ignore study classes, test is already covered
        if(in_array($selected_class_id, $test_classes)){
          //continue;
        }

        $options_by_class[$selected_class_id]['topics'][$selected_topic_string] = $label;
        $options_by_class[$selected_class_id]['label'] = $taxonomy_storage->load($selected_class_id)->label();
      }
    }

    ksort($options_by_class);


    #dpm($options_by_class);
    return $options_by_class;
  }

  /**
   * Gets array of topics of [$id => $label] given a course ID
   * @param $course_id
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCourseTopics($course_id, $separate_secondary_study = false): array
  {
    $topic_names = [];
    $QwizardGeneral = \Drupal::service('qwizard.general');
    $statics = $QwizardGeneral->getStatics();
    $multiquiz_quizzes = $statics['multiquiz_quizzes'];
    $banlist_topics = $statics['banlist_topics'];
    $study_test_classes = $statics['study_test_classes'];

    $qwiz_storage = \Drupal::entityTypeManager()->getStorage('qwiz');
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $current_qwiz = $qwiz_storage->load($multiquiz_quizzes[$course_id]);
    $qwiz_topics = $current_qwiz->get('topics');
    $topic_ids = [];
    foreach ($qwiz_topics as $topic) {
      $topic_id = $topic->getValue()['target_id'];
      if (in_array($topic_id, $banlist_topics)) {
        continue;
      }
      $topic_ids[] = $topic_id;
    }

    $topic_terms = $taxonomy_storage->loadMultiple($topic_ids);
    foreach($topic_terms as $term) {
      $topic_names[$term->id()] = $term->label();
    }

    $quiz_options = _get_specified_topic_options(true);
    foreach($quiz_options as $key=>$value){
      if(!$separate_secondary_study) {
        foreach ($topic_names as $topic_id => $topic_label) {
          if (str_ends_with($key, $topic_id)) {
            unset($quiz_options[$key]);
          }
        }
      }
      foreach($study_test_classes as $study_test_class){
        if(str_contains('_'.$key.'_', $study_test_class)){
          unset($quiz_options[$key]);
        }
      }

      if(!str_starts_with($key, $course_id)){
        unset($quiz_options[$key]);
      }
    }

    $final_topic_selection = [];
    foreach($topic_names as $topic_id=>$topic_label){
      $final_topic_selection[$course_id.'__'.$topic_id] = $topic_label;
    }
    foreach($quiz_options as $key=>$value){
      $final_topic_selection[$key] = $value;
    }

    #dpm($final_topic_selection);
    return $final_topic_selection;
  }

  /**
   * Get the group querying the taxonomy that holds it.
   *
   * @param int $taxonomy_id
   *
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|\Drupal\taxonomy\Entity\Term|null
   */
  public function getGroup($taxonomy_id) {
    return Term::load($taxonomy_id);
  }

  /**
   * Get all the students from a particular School.
   *
   * @param $school
   *   Node id from school
   *
   * @return array of users.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @notes I think this method needs to be move to some "students" object.
   */
  protected function getStudents($school, $selected_topic = null):array {
    $studentData = $this->studentHandler->getStudents($school, $selected_topic);
    $data = [];
    foreach($studentData as $student){
      $data[$student['id']] = $student['name'];
    }

    return $data;
  }

  /**
   * Checks if group name exists querying the taxonomy.
   * Returns bool true if it exists
   *
   * @param $name
   * @return bool
   */
  protected function existsGroupName($name): bool {
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $this->vid);
    $query->condition('name', $name);
    $tids = $query->execute();

    return !empty($tids);
  }

  public function getGroupsForUser($uid){
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $this->vid);
    $query->condition('field_students', $uid);
    $tids = $query->execute();
    if(empty($tids)) return null;

    $groups =  Term::loadMultiple($tids);

    return $groups;
  }

  /**
   * Get all the courses querying the Taxonomy that holds them.
   *
   * @return array
   */
  public function getCourses(): array {
    $courses = [];
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', 'courses');
    $tids = $query->execute();
    if (count($tids) > 0) {
      $taxonomies = Term::loadMultiple($tids);
      foreach ($taxonomies as $taxonomy) {
        $courses[$taxonomy->id()] = $taxonomy->getName();
      }
    }
    return $courses;
  }

  /**
   * Returns list of students added to the group.
   *
   * @param \Drupal\taxonomy\TermInterface $group
   *   The group term.
   *
   * @return array
   *   The list of students added to the group.
   */
  public function getGroupStudents(TermInterface $group) {
    $students = [];

    if (!$group->hasField('field_students')) {
      return $students;
    }

    $student_accounts = $group->get('field_students')->referencedEntities();
    foreach ($student_accounts as $account) {
      $student_data = [
        'id' => $account->id(),
        'username' => $account->getDisplayName(),
        'email' => $account->getEmail(),
        'last_access' => $account->getLastAccessedTime(),
        'formatted_name' => $this->formatUsername($account),
      ];
      $students[$account->id()] = $student_data;
    }
    // Sort by formatted name.
    uasort($students, function ($a, $b) {
      return strcmp($a['formatted_name'], $b['formatted_name']);
    });

    return $students;
  }

  /**
   * Returns list of administrators added to the group.
   *
   * @param \Drupal\taxonomy\TermInterface $group
   *   The group term.
   *
   * @return array
   *   The list of administrators added to the group.
   */
  public function getGroupAdmins(TermInterface $group) {
    $admins = [];

    if (!$group->hasField('field_administrator')) {
      return $admins;
    }

    $admin_accounts = $group->get('field_administrator')->referencedEntities();
    foreach ($admin_accounts as $account) {
      $admin_data = [
        'id' => $account->id(),
        'username' => $account->getDisplayName(),
        'email' => $account->getEmail(),
        'last_access' => $account->getLastAccessedTime(),
        'formatted_name' => $this->formatUsername($account),
      ];
      $admins[$account->id()] = $admin_data;
    }
    // Sort by formatted name.
    uasort($admins, function ($a, $b) {
      return strcmp($a['formatted_name'], $b['formatted_name']);
    });

    return $admins;
  }

  /**
   * Returns course attached to the group.
   *
   * @param \Drupal\taxonomy\TermInterface $group
   *   The group term.
   * @param bool $loaded
   *   Flag to indicate if just course id or loaded entity will be passed.
   *
   * @return int|TermInterface
   *   Either an id of the course term or loaded course term entity.
   */
  public function getGroupCourse(TermInterface $group, bool $loaded = FALSE) {
    $course = NULL;
    $courses = $group->get('field_course')->referencedEntities();
    if (!empty($courses)) {
      // Course reference is single valued field.
      $course = reset($courses);
      if (!$loaded) {
        return $course->id();
      }
    }
    return $course;
  }

  /**
   * Formats the name of the user using first & last name.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account object.
   * @param string $format
   *   The desired format in which username will get formatted.
   *
   * @return string
   *   The formatted name of the user.
   */
  public function formatUsername(UserInterface $account, string $format = '<last_name>, <first_name>') {
    $replacements = [
      '<last_name>' => $account->get('field_last_name')->getString(),
      '<first_name>' => $account->get('field_first_name')->getString(),
    ];
    foreach ($replacements as $search => $replace) {
      $format = str_replace($search, $replace, $format);
    }
    return $format;
  }

  /**
   * Returns the custom tokens, mainly used for group email.
   *
   * @return array
   *   An array of custom tokens.
   */
  public function getCustomTokens($token_groups = []) {
    $tokens = [
      'user' => [
        'first_name' => $this->t('The first name of user. '),
        'last_name' => $this->t('The last name of user. '),
        'combined_name' => $this->t('The combined name of user. Usually in the format "LAST NAME, FIRST NAME.'),
        'activation_link' => $this->t('The activation link for the user.'),
        'one_time_link' => $this->t('The one time login link for the user.'),
        'password_reset_timeout' => $this->t('The one time login link timout. E.g. 1 day or 3 days.'),
      ],
      'group' => [
        'course' => $this->t('The course selected for this group.'),
      ],
      'subscription' => [
        'course' => $this->t('The course selected for the active subscription.'),
        'start' => $this->t('The start date of active subscription in <code>m/d/Y</code> format.'),
        'end' => $this->t('The end date of active subscription in <code>m/d/Y</code> format.'),
      ],
    ];

    // Prepare tokens.
    $custom_tokens = [];
    foreach ($tokens as $token_group => $token_data) {
      foreach ($token_data as $token_name => $description) {
        $actual_token = '{' . $token_group . '.' . $token_name . '}';
        $custom_tokens[$token_group][$actual_token] = $description;
        $custom_tokens['all'][$actual_token] = $description;
      }
    }

    if (empty($token_groups)) {
      // Send all tokens.
      return $custom_tokens['all'];
    }

    $group_tokens = [];
    foreach ($token_groups as $group_name) {
      if (!empty($custom_tokens[$group_name])) {
        $group_tokens = array_merge($group_tokens, $custom_tokens[$group_name]);
      }
    }
    return $group_tokens;
  }

  /**
   * Replaces custom tokens using provided data.
   *
   * @param array $tokens
   *   The tokens to replace.
   * @param array $data
   *   The data to be used for token replacement.
   * @param bool $clear
   *   Whether to clear token if value is not found.
   *
   * @return array
   *   An array of custom tokens.
   */
  public function getReplacedCustomTokens(array $tokens = [], array $data = [], $clear = TRUE) {
    $replacements = [];
    // Token pattern.
    $pattern = '/{([^\.]+)\.([^}]+)}/';
    foreach ($tokens as $token) {
      if (preg_match($pattern, $token, $matches)) {
        [, $token_group, $field] = $matches;
      }
      if ($clear) {
        $replacements[$token] = '';
      }
      // Replace user tokens.
      if ($token_group == 'user' && !empty($data['user']) && $data['user'] instanceof UserInterface) {
        $account = $data['user'];
        switch ($field) {
          case 'first_name':
            $replacements[$token] = $account->get('field_first_name')
              ->getString();
            break;
          case 'last_name':
            $replacements[$token] = $account->get('field_last_name')
              ->getString();
            break;
          case 'combined_name':
            $replacements[$token] = $this->formatUsername($account);
            break;
          case 'activation_link':
            $replacements[$token] = \Drupal::service('zukuuser.user_manager')
              ->getActivationUrl($account, TRUE);
            break;
          case 'one_time_link':
            $replacements[$token] = user_pass_reset_url($account) . '/login';
            break;
          case 'password_reset_timeout':
            $timeout = \Drupal::config('user.settings')
              ->get('password_reset_timeout');
            $replacements[$token] = \Drupal::service('date.formatter')
              ->formatInterval($timeout);
            break;
        }
      }
      // Replace group tokens.
      if ($token_group == 'group' && !empty($data['group']) && $data['group'] instanceof TermInterface) {
        $group = $data['group'];
        if ($field == 'course') {
          $course = $this->getGroupCourse($group, TRUE);
          $replacements[$token] = $course->getName();
        }
      }
      // Replace subscription tokens. We decide subscription from user passed.
      if ($token_group == 'subscription' && !empty($data['user']) && $data['user'] instanceof UserInterface) {
        $account = $data['user'];
        $subscriptions = \Drupal::service('qwsubs.subscription_handler')->getUserSubscriptions($account->id(), NULL, TRUE);
        if (!empty($subscriptions)) {
          $subscription = reset($subscriptions);
          if ($field == 'course') {
            $courses = $subscription->get('course')->referencedEntities();
            $course = reset($courses);
            $replacements[$token] = $course->getName();
          }
          if ($field == 'start' || $field == 'end') {
            $sub_date_format = 'm-d-Y';
            $sub_term = $subscription->getLastSubTerm(FALSE);
            if ($sub_term->hasField($field)) {
              $sub_date = $sub_term->get($field)->getString();
              $sub_time = strtotime($sub_date);
              $replacements[$token] = \Drupal::service('date.formatter')->format($sub_time, 'custom', $sub_date_format);
            }
          }
        }
      }
    }
    return $replacements;
  }

}
