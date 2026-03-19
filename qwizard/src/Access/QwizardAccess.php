<?php

namespace Drupal\qwizard\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Determines access to routes based on roles.
 *
 * To achieve this, we implement a class with AccessInterface and use that to
 * check access.
 *
 * Our module is called menu_example, this file will be placed under
 * menu_example/src/Access/CustomAccessCheck.php.
 *
 * The @link menu_example_services.yml @endlink contains entry for this service
 * class.
 *
 * @see https://www.drupal.org/docs/8/api/routing-system/access-checking-on-routes
 */
class QwizardAccess implements AccessInterface {

  /**
   * Checks access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    // Get url parameters.
    $parameters = $route_match->getParameters();
    if ($parameters->has('qwiz')) {
      // @todo: Handle qwiz access.
      $qwiz = $parameters->get('qwiz');
    }
    if ($parameters->has('user')) {
      // @todo: Handle account based access.
      $request_account = $parameters->get('user');
    }
    if ($parameters->has('qwiz_result')) {
      // @todo: Handle account based access.
      $qwiz_result = $parameters->get('qwiz_result');
    }

    // If the user is authenticated, return TRUE.
    return AccessResult::allowedIf($account->isAuthenticated());
  }

}
