<?php
/*-------------------------------------------------------+
| SYSTOPIA Resource Event                                |
| Copyright (C) 2022 SYSTOPIA                            |
| Author: J. Schuppe (schuppe@systopia.de)               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

namespace Civi\Resourceevent;

use Civi\Api4\CustomField;
use Civi\Api4\OptionValue;
use Civi\Api4\Participant;
use Civi\Api4\Resource;
use Civi\Api4\ResourceAssignment;
use CRM_Resourceevent_ExtensionUtil as E;

class Utils {

  public static function getResourceRole($map = FALSE) {
    $role = OptionValue::get(FALSE)
      ->addSelect('value', 'label')
      ->addWhere('option_group_id.name', '=', 'participant_role')
      ->addWhere('name', '=', 'human_resource')
      ->execute()
      ->single();
    return $map ? [$role['value'] => $role['label']] : $role['value'];
  }

  public static function getDefaultParticipantStatus($class) {
    if (!in_array(strtolower($class), ['positive', 'negative'])) {
      throw new \Exception(E::ts('Unknown participant status class %1.', [1 => $class]));
    }
    if (!$status_id = \Civi::settings()->get('resourceevent_participant_status_' . strtolower($class))) {
      // If no specific status is configured, use the one with the most
      // significant weight.
      $result = civicrm_api3(
        'ParticipantStatusType',
        'get',
        [
          'sequential' => 1,
          'return' => ['id'],
          'class' => 'Positive',
          'is_active' => 1,
          'options' => ['sort' => 'weight ASC'],
        ]
      );
      if (empty($result['count'])) {
        throw new \Exception(E::ts('No active participant status with class %1', [1 => $class]));
      }
      $status_id = reset($result['values'])['id'];
    }
    return $status_id;
  }

  public static function getResourceForParticipant($participant_id) {
    $participant = Participant::get(FALSE)
      ->addSelect('contact_id', 'resource_information.resource_demand')
      ->addWhere('id', '=', $participant_id)
      ->execute()
      ->single();
    try {
      $resource = Resource::get(FALSE)
        ->addWhere('entity_table', '=', 'civicrm_contact')
        ->addWhere('entity_id', '=', $participant['contact_id'])
        ->execute()
        ->single();
    }
    catch (\Exception $exception) {
      // Participant contact is not a resource.
      $resource = NULL;
    }

    return $resource;
  }

  public static function getResourceAssignmentForParticipant($participant_id) {
    $participant = Participant::get(FALSE)
      ->addSelect('contact_id', 'resource_information.resource_demand')
      ->addWhere('id', '=', $participant_id)
      ->execute()
      ->single();
    try {
      $resource = Resource::get(FALSE)
        ->addWhere('entity_table', '=', 'civicrm_contact')
        ->addWhere('entity_id', '=', $participant['contact_id'])
        ->execute()
        ->single();
      $resource_assignment = ResourceAssignment::get(FALSE)
        ->addWhere('resource_id', '=', $resource['id'])
        ->addWhere('resource_demand_id', '=', $participant['resource_information.resource_demand'])
        ->execute()
        ->single();
    }
    catch (\Exception $exception) {
      // Participant contact is not a resource or no resource assignment found.
      $resource_assignment = NULL;
    }

    return $resource_assignment;
  }

}
