<?php

namespace Drupal\qwreporting\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\qwreporting\GroupsInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Returns responses for Quiz Wizard Reporting routes.
 */
class QwreportingController extends ControllerBase {

  /**
   * Group service.
   *
   * @var \Drupal\qwreporting\GroupsInterface
   */
  private $groups;

  /**
   * QwreportingController constructor.
   *
   * @param \Drupal\qwreporting\GroupsInterface $groups
   *   Group object.
   */
  public function __construct(GroupsInterface $groups) {
    $this->groups = $groups;
  }

  /**
   * Get object from container services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Drupal Container.
   *
   * @return \Drupal\qwreporting\Controller\QwreportingController|mixed|object|null
   *   return self with dependencies
   */
  public static function create(ContainerInterface $container) {
    $group = $container->get('qwreporting.groups');
    return new static($group);
  }

  /**
   * Builds the response.
   *
   * @return array
   *   array with theme and variables to twig
   */
  public function build():array {
    \Drupal::service('page_cache_kill_switch')->trigger();
    $groups = $this->groups->getGroups();
    if(empty($groups)){
      \Drupal::messenger()->addWarning('You are not assigned to any active reporting groups.');
      throw new AccessDeniedHttpException();
    }

    try {
      $school = $this->groups->getFacultySchoolForUser();
    } catch (\Exception $e) {
      // If getFacultySchoolForUser throws an exception, it just means that the
      // current user doesn't have any schools assigned. Ignore it.
      $school = [];
    }

    return [
      '#theme' => 'qwreporting_homepage',
      '#groups' => $groups,
      '#school' => $school,
    ];
  }

}
