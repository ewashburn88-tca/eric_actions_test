<?php

namespace Drupal\qwreporting;

/**
 * Interface for group functionality.
 *
 * @ingroup qwreporting
 */
interface GroupsInterface {

  /**
   * Get groups for current user.
   *
   * @return array
   *   Groups in array with basic information.
   */
  public function getGroups():array;

  /**
   * Create and storage group.
   *
   * @param array $array
   *   Data for group creation.
   *
   * @return int|false
   *   Id of new group in case of success or FALSE in case of failure.
   */
  public function createGroup(array $array);

  /**
   * Edit and storage group.
   *
   * @param int $id
   *   Group id.
   * @param array $array
   *   Group details.
   *
   * @return int|false
   *   Id of group in case of success and false in case of failure.
   */
  public function editGroup(int $id, array $array);


  /**
   * Get all the data of a group from id.
   *
   * @param int $group
   *   Id of Group.
   *
   * @return array
   *   Id, Name, Description and Course.
   */
  public function getGroupData($group): array;

}
