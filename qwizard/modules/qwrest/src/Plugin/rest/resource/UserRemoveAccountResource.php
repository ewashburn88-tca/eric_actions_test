<?php

namespace Drupal\qwrest\Plugin\rest\resource;

use Dompdf\Exception;
use Drupal\Component\Serialization\Json;
// use Drupal\qwizard\CourseHandler;
// use Drupal\qwizard\QwizardGeneral;
// use Drupal\qwsubs\Entity\Subscription;
// use Drupal\qwsubs\SubscriptionHandler;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\zuku\ZukuGeneral;
use Drupal\user\Entity\User;


/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "user_remove_account_resource",
 *   label = @Translation("User Remove Account Resource"),
 *   uri_paths = {
 *     "canonical" = "/api-v1/user-remove-account",
 *     "create" = "/api-v1/user-remove-account"
 *   }
 * )
 */
class UserRemoveAccountResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;
  // protected $userStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance              = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->logger      = $container->get('logger.factory')->get('qwrest');
    $instance->currentUser = $container->get('current_user');

    return $instance;
  }

  /**
   * Responds to POST requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    if (!$this->currentUser->hasPermission('access content') || empty($this->currentUser->id())) {
      throw new AccessDeniedHttpException();
    }

    $this->removeUserAccount();

    $payload_response = ['message' => t('Your account has been removed')];
    $response = new ModifiedResourceResponse($payload_response, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  /**
   * Responds to GET requests.
   *
   * @param string $payload
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get() {
    \Drupal::service('page_cache_kill_switch')->trigger();
    if (!$this->currentUser->hasPermission('access content') || empty($this->currentUser->id())) {
      throw new AccessDeniedHttpException();
    }

    // Removed for GET
    //$this->removeUserAccount();

    //$payload_response = ['message' => t('Your account has been removed')];
    $payload_response = ['message' => t('use POST')];
    $response = new ModifiedResourceResponse($payload_response, 200);
    $response->setMaxAge(-1);
    return $response;
  }

  public function removeUserAccount(){
    $uid = $this->currentUser->id();

    \Drupal::logger('user_removed_account_api')->notice('A request was made to remove the account of user '.$uid);
    \Drupal::service('zukucomment.zukucomment')->addComment('Account was removed from action in app.', $uid, 'user');

    $user = User::load($uid);
    $user->set('status', 0);
    $user->save();

    /*// Tell Drupal to cancel this user.
    // The third argument can be one of the following:
    //   - user_cancel_block: disable user, leave content
    //   - user_cancel_block_unpublish: disable user, unpublish content
    //   - user_cancel_reassign: delete user, reassign content to uid=0
    //   - user_cancel_delete: delete user, delete content
    user_cancel(array(), $uid, 'user_cancel_block');

    // user_cancel() initiates a batch process. Run it manually.
    $batch =& batch_get();
    $batch['progressive'] = FALSE;
    batch_process();*/


    user_logout();
  }
}

