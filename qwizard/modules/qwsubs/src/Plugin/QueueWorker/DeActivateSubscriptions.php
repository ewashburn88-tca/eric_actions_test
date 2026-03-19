<?php

namespace Drupal\qwsubs\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\qwsubs\SubscriptionLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deactivates subscriptions that are active and removes the user's role.
 *
 * @QueueWorker(
 *   id = "deactivate_subscriptions",
 *   title = @Translation("Deactivates subscription entities based on term info"),
 *   cron = {"time" = 30}
 * )
 */
class DeActivateSubscriptions extends QueueWorkerBase {

  /**
   * @param mixed $data
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function processItem($data) {
    $subterm_storage = \Drupal::entityTypeManager()->getStorage('subterm');
    $subterm = $subterm_storage->load($data);
    // @todo: handle if a sub is removed while still in queue.
    if (!empty($subterm)) {
      $sub = $subterm->getSubscription();
      if (!empty($sub)) {
        $result = $sub->deActivateSubscription();
      }
    }
  }

}
