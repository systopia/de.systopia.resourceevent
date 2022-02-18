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

require_once 'resourceevent.civix.php';
// phpcs:disable
use Civi\Api4;
use CRM_Resourceevent_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function resourceevent_civicrm_config(&$config) {
  _resourceevent_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function resourceevent_civicrm_xmlMenu(&$files) {
  _resourceevent_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function resourceevent_civicrm_install() {
  _resourceevent_civix_civicrm_install();

  // Synchronise participant role.
  $customData = new CRM_Resourceevent_CustomData(E::LONG_NAME);
  $customData->syncOptionGroup(E::path('resources/option_group_participant_role.json'));

  // Synchronise custom field for storing resource demand on participants.
  // Note: This can't be done with CRM_Resourceevent_CustomData because we don't
  // know the "role ID", which is the OptionValue's value.
  Api4\CustomGroup::create()
    ->setValues([
      'name' => 'resource_information',
      'title' => 'Resource Information',
      'extends' => 'Participant',
      'extends_entity_column_id' => CRM_Core_OptionGroup::values(
        'custom_data_type',
        TRUE,
        FALSE,
        FALSE,
        NULL,
        'name'
      )['ParticipantRole'],
      'extends_entity_column_value' => Api4\OptionValue::get()
        ->addSelect('value')
        ->addWhere('option_group_id:name', '=', 'participant_role')
        ->addWhere('name', '=', 'human_resource')
        ->execute()
        ->column('value'),
      // Note: "is_reserved" hides the custom field group in the UI.
      'is_reserved' => 1,
      'table_name' => 'civicrm_value_resource_information',
    ])
    ->addChain('resource_demand', Api4\CustomField::create()->setValues([
      'name' => 'resource_demand',
      'label' => 'Resource Demand',
      'custom_group_id' => '$id',
      'html_type' => 'Text',
      'data_type' => 'Int',
      'is_required' => 1,
      'is_searchable' => 0,
      'is_search_range' => 0,
      'is_view' => 1,
      'in_selector' => 0,
      'column_name' => 'resource_demand'
    ]))
    ->execute();

  // TODO: Add a foreign key constraint to the custom field, allowing only
  //       resource demand IDs as values. This currently fails due to
  //       incompatible database fields (int vs. unsigned int).
//  CRM_Core_DAO::singleValueQuery("
//ALTER TABLE civicrm_value_resource_information
//    ADD CONSTRAINT FK_civicrm_value_resource_information_resource_demand FOREIGN KEY (resource_demand)
//        REFERENCES civicrm_resource_demand(id);
//");
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function resourceevent_civicrm_postInstall() {
  _resourceevent_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function resourceevent_civicrm_uninstall() {
  _resourceevent_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function resourceevent_civicrm_enable() {
  _resourceevent_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function resourceevent_civicrm_disable() {
  _resourceevent_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function resourceevent_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _resourceevent_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function resourceevent_civicrm_managed(&$entities) {
  _resourceevent_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Add CiviCase types provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function resourceevent_civicrm_caseTypes(&$caseTypes) {
  _resourceevent_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Add Angular modules provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function resourceevent_civicrm_angularModules(&$angularModules) {
  // Auto-add module files from ./ang/*.ang.php
  _resourceevent_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function resourceevent_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _resourceevent_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function resourceevent_civicrm_entityTypes(&$entityTypes) {
  _resourceevent_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function resourceevent_civicrm_themes(&$themes) {
  _resourceevent_civix_civicrm_themes($themes);
}
