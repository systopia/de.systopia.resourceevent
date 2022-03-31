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
use CRM_Resourceevent_ExtensionUtil as E;

class Utils {

  public static function getResourceRole() {
    return OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id.name', '=', 'participant_role')
      ->addWhere('name', '=', 'human_resource')
      ->execute()
      ->single()['value'];
  }

  public static function getDemandCustomFieldId() {
    return CustomField::get(FALSE)
      ->addWhere('custom_group_id.name', '=', 'resource_information')
      ->addWhere('name', '=', 'resource_demand')
      ->addSelect('id')
      ->execute()
      ->single()['id'];
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

}
