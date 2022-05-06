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

use CRM_Resourceevent_ExtensionUtil as E;

/*
* Settings metadata file
*/

return [
  'resourceevent_default_participant_status_id_positive' => [
    'group_name' => E::ts('CiviResource Event Settings'),
    'group' => 'resourceevent',
    'name' => 'resourceevent_default_participant_status_id_positive',
    'type' => 'Integer',
    'html_type' => 'select',
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'is_required' => 0,
    'title' => E::ts('Default positive participant status'),
    'description' => E::ts('Default positive participant status to set participants to when assigning a resource.'),
    'html_attributes' => [
      'class' => 'crm-select2',
    ],
    'pseudoconstant' => [
      'table' => 'civicrm_participant_status_type',
      'keyColumn' => 'id',
      'labelColumn' => 'label',
      'optionEditPath' => 'civicrm/admin/participant_status',
      'condition' => 'class = "Positive"',
    ],
    'settings_pages' => [
      'resourceevent' => [
        'weight' => 10,
      ]
    ],
  ],
  'resourceevent_default_participant_status_id_negative' => [
    'group_name' => E::ts('CiviResource Event Settings'),
    'group' => 'resourceevent',
    'name' => 'resourceevent_default_participant_status_id_negative',
    'type' => 'Integer',
    'html_type' => 'select',
    'add' => '4.3',
    'is_domain' => 1,
    'is_contact' => 0,
    'is_required' => 0,
    'title' => E::ts('Default negative participant status'),
    'description' => E::ts('Default negative participant status to set participants to when un-assigning a resource.'),
    'html_attributes' => [
      'class' => 'crm-select2',
    ],
    'pseudoconstant' => [
      'table' => 'civicrm_participant_status_type',
      'keyColumn' => 'id',
      'labelColumn' => 'label',
      'optionEditPath' => 'civicrm/admin/participant_status',
      'condition' => 'class = "Negative"',
    ],
    'settings_pages' => [
      'resourceevent' => [
        'weight' => 20,
      ]
    ],
  ],
];
