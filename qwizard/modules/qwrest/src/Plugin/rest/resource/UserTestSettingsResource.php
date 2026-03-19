<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Drupal\Component\Serialization\Json;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;
use mysql_xdevapi\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "user_test_settings_resource",
 *   label = @Translation("User test settings resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/user-test-settings",
 *     "create" = "/api-v1/qwiz-session"
 *   }
 * )
 */
class UserTestSettingsResource extends ResourceBase
{

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  protected $activeProfile = null;
  protected $activeProfileMarkedQuestions = null;
  protected $INactiveProfileMarkedQuestions = null;
  protected $input_params = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger = $container->get('logger.factory')->get('qwrest');
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Responds to GET requests.
   *
   * Loads a test session for review.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get()
  {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $payload = [];

    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    $active_profile = $this->getQwizardProfile();

    $payload['test_settings'] = [
      'theme' => 'tester-theme-light',
      'completionGoalDate' => null,
      'hideTestIcons' => 0,
      'specialAccommodations' => 0,
      'timePerQuestion' => 90,
      'markedQuestions' => null,
      'course_id' => null, //Aziz
      'markedCards' => null,
    ];

    // getting marked cards and questions
    // $database = \Drupal::database()->select('marked_question_field_data', 'mq')
    //   ->fields('n', ['nid', 'type']);
    // $database->join('node', 'n', 'n.nid=mq.question');
    // $database->condition('mq.status', 1);
    // $database->condition('mq.uid', $this->currentUser->id());
    // $results = $database->execute()->fetchAll();

    $course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();
    // If user do not have active subscription, the course will be null.
    $course_label = !empty($course) ? $course->label() : NULL;
    $QW_general_service = \Drupal::service('qwizard.general');
    $markedQuestions = $QW_general_service->getMarkedQuestions(['type' => 'qw_simple_choice']);
    $markedCards     = $QW_general_service->getMarkedQuestions(['type' => 'qw_flashcard']);

    if (!empty($active_profile)) {
      $payload['test_settings'] = array_merge($payload['test_settings'], [
        'theme' => $active_profile->field_tester_theme->value,
        'completionGoalDate' => $active_profile->field_completion_date->value,
        'hideTestIcons' => $active_profile->field_hide_test_icons->value,
        'specialAccommodations' => $active_profile->field_special_accommodations->value,
        'timePerQuestion' => $active_profile->field_time_options->value,
        'markedQuestions' => array_values($markedQuestions),
        'course_id' => $course_label,
        'markedCards' => array_values($markedCards),
        'current_lang' => \Drupal::languageManager()->getCurrentLanguage()->getId(),
      ]);
    }

    $response = new ResourceResponse($payload, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Responds to PATCH requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function patch()
  {
    \Drupal::service('page_cache_kill_switch')->trigger();

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }
    try {
      $this->activeProfile = $this->getQwizardProfile();

      // Get request params
      $get_params_to_get = [
        'theme',
        'completionGoalDate',
        'specialAccommodations',
        'timePerQuestion',
        'markedQuestions',
        'markedCards',
        'markedQuestions',
        'markedCards',
        'current_lang'
      ];
      $rest_general_service = \Drupal::service('qwrest.general');
      $QW_general_service = \Drupal::service('qwizard.general');
      $input_params = $rest_general_service->getInputsParams($get_params_to_get, [], []);
      $this->input_params = $input_params;

      // If debug print payload to log.
      if (qwizard_in_debug_mode()) {
        $prefix_text = 'Patch Payload rcvd:';
         $this->log_debug($input_params, $prefix_text);
      }

      // Set fields on profile based on patch request inputs
      if (isset($input_params['theme'])) {
        $this->activeProfile->set('field_tester_theme', $input_params['theme']);
      }
      if (isset($input_params['completionGoalDate'])) {
        $this->activeProfile->set('field_completion_date', $input_params['completionGoalDate']);
      }
      if (isset($input_params['hideTestIcons'])) {
        $this->activeProfile->set('field_hide_test_icons', $input_params['hideTestIcons']);
      }
      if (isset($input_params['specialAccommodations'])) {
        $this->activeProfile->set('field_special_accommodations', $input_params['specialAccommodations']);
      }
      if(isset($input_params['timePerQuestion'])){
        $this->activeProfile->set('field_time_options', $input_params['timePerQuestion']);
      }

      if (isset($input_params['current_lang'])) {
        $languages = \Drupal::languageManager()->getLanguages();
        if (array_key_exists($input_params['current_lang'], $languages)) {
          $uid = $this->currentUser->id();
          $user = User::load($uid);
          $user->set('preferred_langcode', $input_params['current_lang']);
          $user->save();
        }
      }

      $course = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();

      if(isset($input_params['markedQuestions']) || isset($input_params['markedCards'])) {
        $this->activeProfileMarkedQuestions = $QW_general_service->getMarkedQuestions(['status' => 1]);
        $this->INactiveProfileMarkedQuestions = $QW_general_service->getMarkedQuestions(['status' => 0]);

        //Merge the two question arrays
        $inputActiveQuestions = [];
        if (!empty($input_params['markedQuestions'])) {
          foreach ($input_params['markedQuestions'] as $question_id) {
            $inputActiveQuestions[$question_id] = $question_id;
          }
        }
        if (!empty($input_params['markedCards'])) {
          foreach ($input_params['markedCards'] as $question_id) {
            $inputActiveQuestions[$question_id] = $question_id;
          }
        }

        // Make sure questions from input are set to active
        if (!empty($inputActiveQuestions)) {
          foreach ($inputActiveQuestions as $question_id) {
            $this->assignQuestionToUser($question_id);
          }
        }


        // Gets array of currently saved marked and get diff with $input_params['markedQuestions'].
        // Then loops through result and un-publishes missing ones
        // First re-get active questions array with fresh data
        $types_to_check_to_unpublish = 'all';
        if(isset($input_params['markedQuestions']) && !isset($input_params['markedCards'])){
          $types_to_check_to_unpublish = 'qw_simple_choice';
        }
        elseif(!isset($input_params['markedQuestions']) && isset($input_params['markedCards'])){
          $types_to_check_to_unpublish = 'qw_flashcard';
        }
        $this->activeProfileMarkedQuestions = $QW_general_service->getMarkedQuestions(['status' => 1, 'type' => $types_to_check_to_unpublish]);

        $questions_to_set_inactive = [];
        foreach ($this->activeProfileMarkedQuestions as $activeQuestion) {
          if (!in_array($activeQuestion, $inputActiveQuestions)) {
            $questions_to_set_inactive[$activeQuestion] = $activeQuestion;
          }
        }
        $this->unpublishQuestionsByIDs($questions_to_set_inactive);


        if (isset($input_params['markedQuestions'])) {
          $json = Json::encode($input_params['markedQuestions']);
          $this->activeProfile->set('field_marked_questions', $json);
        }
        if (isset($input_params['markedCards'])) {
          $json = Json::encode($input_params['markedCards']);
          $this->activeProfile->set('field_marked_cards', $json);
        }
      }

      $this->activeProfile->save();

      // If debug print payload to log.
      if (qwizard_in_debug_mode()) {
        $prefix_text = 'Patch Payload sent:';
        $this->log_debug($input_params, $prefix_text);
      }

      $response = new ModifiedResourceResponse($input_params, 204);
      $response->setMaxAge(-1);
      return $response;
    }
    catch (\Exception $e) {
      $response = new ModifiedResourceResponse($e->getMessage(), 404);
      $response->setMaxAge(-1);
      return $response;
    }
  }

  /**
   * Get the user's profile. If one does not exist yet, create it.
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|Profile|false|mixed|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getQwizardProfile()
  {
    if (!empty($this->activeProfile)) {
      return $this->activeProfile;
    }

    // Use loading function provided by profile module. This function checks for
    // default profile & status to make sure we get correct active profile.
    $active_profile = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByUser($this->currentUser, 'qwizard_profile');

    if (empty($active_profile)) {
      // Create new profile type to use.
      $profile = Profile::create([
        'type' => 'qwizard_profile',
      ]);
      $active_profile = $profile;
    }

    $this->activeProfile = $active_profile;
    return $active_profile;
  }


  protected function assignQuestionToUser($question_id)
  {
    $question_id = (string)$question_id;
    $uid = $this->currentUser->id();
    $course_id = \Drupal::service('qwizard.coursehandler')->getCurrentCourse();//Aziz


    if ($this->activeProfileMarkedQuestions === null || $this->INactiveProfileMarkedQuestions === null) {
      throw new \Exception('assignQuestionToProfile required parameters are not available');
    }

    if (in_array($question_id, $this->INactiveProfileMarkedQuestions)) {
      // If it exists already but is unpublished, activate it
      $query = \Drupal::database()->update('marked_question_field_data');
      $query->fields(['status' => 1, 'changed' => time()]);
      $query->condition('question', $question_id);
      $query->condition('uid', $uid);
      $result = $query->execute();
      \Drupal::entityTypeManager()->getStorage('marked_question')->resetCache([$question_id]);

    } elseif (in_array($question_id, $this->activeProfileMarkedQuestions)) {
      // Do nothing, the question already exist and is published
    } else {
      // If it does not exist at all yet, create it
      $markedEntity = \Drupal::entityTypeManager()->getStorage('marked_question')->create([
        'question' => $question_id,
        'uid' => $uid,
        'course' => $course_id //Aziz
      ]);
      $markedEntity->save();
    }

    // Add to active array, remove from inactive array
    if(!in_array($question_id,  $this->activeProfileMarkedQuestions)) {
      $this->activeProfileMarkedQuestions[] = $question_id;
    }
    if (($key = array_search($question_id, $this->INactiveProfileMarkedQuestions)) !== false) {
      unset($this->INactiveProfileMarkedQuestions[$key]);
    }

    return null;
  }

  public function unpublishQuestionsByIDs($question_ids){
    if ($this->activeProfileMarkedQuestions === null || $this->INactiveProfileMarkedQuestions === null) {
      throw new \Exception('unpublishQuestionsByIDs required parameters are not available');
    }

    $uid = $this->currentUser->id();

    // Avoid dealing with unsetting a question if it is not already active
    foreach($question_ids as $key=>$question_id){
      if(!in_array($question_id, $this->activeProfileMarkedQuestions)){
        unset($question_ids[$key]);
      }
    }

    if(count($question_ids) > 1){
      \Drupal::logger('marked_questions')->debug('The following question IDs were just attempted to be all unpublished. There should only be one. Question IDs were '.json_encode($question_ids).' Input payload was '.json_encode($this->input_params).' $_SERVER is '.json_encode($_SERVER));
    }

    // @todo, could be done in one query
    foreach($question_ids as $question_id){
      $query = \Drupal::database()->update('marked_question_field_data');
      $query->fields(['status' => 0, 'changed' => time()]);
      $query->condition('question', $question_id);
      $query->condition('uid', $uid);
      $result = $query->execute();

      // Update class array, removing from active and adding to inactive
      if (($key = array_search($question_id, $this->activeProfileMarkedQuestions)) !== false) {
        unset($this->activeProfileMarkedQuestions[$key]);
      }
      if(!in_array($question_id,  $this->INactiveProfileMarkedQuestions)) {
        $this->INactiveProfileMarkedQuestions[] = $question_id;
      }

      // @TODO this is here to prevent multiple questions from being unmarked in a single request
      break;
    }
  }

  /*protected function saveMarkedItems($items) {
    $query =\Drupal::database()->update('marked_question_field_data');
    $query->fields(['status' => 0, 'changed' => time()]);
    $query->condition('uid', $this->currentUser->id());
    if($items) {
      $query->condition('question', $items, 'NOT IN');
    }
    $result = $query->execute();

    if(is_array($items) && count($items)) {
      foreach($items as $id) {
        // check if the question exists
        $result = \Drupal::database()->select('marked_question_field_data', 'm')
        ->fields('m', ['id', 'status'])
        ->condition('question', $id)
        ->condition('uid', $this->currentUser->id())
        ->execute()
        ->fetchAll();

        if(count($result)) {
          $markedItem = current($result);
          // if it exists, but not active, activate it

          // var_dump($markedItem->status); exit;
          if($markedItem->status != 1) {
            $query =\Drupal::database()->update('marked_question_field_data');
            $query->fields(['status' => 1, 'changed' => time()]);
            $query->condition('id', $markedItem->id);
            $result = $query->execute();
          }
        } else {
          $markedEntity = \Drupal::entityTypeManager()->getStorage('marked_question')->create([
            'question' => $id,
            'uid'  => $this->currentUser->id(),
          ]);
          $markedEntity->save();
        }
      }
    }
  }*/

  /**
   * Logs debug info.
   *
   * @param $output
   * @param $prefix_text
   */
  private function log_debug($output, $prefix_text)
  {
    $prefix_text = \Drupal::service('path.current')->getPath() . '<br>' . $prefix_text;
    $json = json_encode($output, JSON_PRETTY_PRINT);
    \Drupal::logger('QwizSessionResource Debug')->debug($prefix_text . '<br><pre>' . $json . '</pre>');
  }
}
