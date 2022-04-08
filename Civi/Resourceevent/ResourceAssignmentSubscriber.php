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

use Civi\Api4\Participant;
use Civi\Api4\Resource;
use Civi\Api4\ResourceDemand;
use Civi\Core\DAO\Event\PostDelete;
use Civi\Core\DAO\Event\PostUpdate;
use CRM_Resource_BAO_ResourceAssignment;
use CRM_Resourceevent_ExtensionUtil as E;

class ResourceAssignmentSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      'civi.dao.postInsert' => 'insertUpdateResourceAssignment',
      'civi.dao.postUpdate' => 'insertUpdateResourceAssignment',
      'civi.dao.postDelete' => 'deleteResourceAssignment',
    ];
  }

  /**
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   * @throws \Exception
   */
  public function insertUpdateResourceAssignment(PostUpdate $event) {
    if ($event->object instanceof CRM_Resource_BAO_ResourceAssignment) {
      // TODO: Avoid infinite loops between post hooks for ResourceAssignment
      //       and Participant entities.

      $resource_role = Utils::getResourceRole();
      [$resource, $resource_demand] = self::getResourceAssignmentContext($event->object);
      if (
        $resource['entity_table'] == 'civicrm_contact'
        && $resource_demand['entity_table'] == 'civicrm_event'
      ) {
        $participants = Participant::get(FALSE)
          ->addWhere('contact_id', '=', $resource['entity_id'])
          ->addWhere('event_id', '=', $resource_demand['entity_id'])
          ->addWhere('role_id', 'LIKE', '%' . implode(\CRM_Core_DAO::VALUE_SEPARATOR, [$resource_role]) . '%')
          ->execute();
        switch ($participants->count()) {
          case 0:
            Participant::create(FALSE)
              ->addValue('contact_id', $resource['entity_id'])
              ->addValue('event_id', $resource_demand['entity_id'])
              ->addValue('role_id', [$resource_role])
              ->addValue('resource_information.resource_demand', $resource_demand['id'])
              ->addValue('status_id', Utils::getDefaultParticipantStatus('positive'))
              ->addValue('register_date', date('Y-m-d H:i:s'))
              ->execute();
            break;
          case 1:
            $participant = $participants->single();
            Participant::update(FALSE)
              ->addWhere('id', '=', $participant['id'])
              ->addValue('status_id', Utils::getDefaultParticipantStatus('positive'))
              ->execute();
            break;
          default:
            throw new \Exception(E::ts(
              'More than one participant found with role %1.',
              [1 => \CRM_Event_PseudoConstant::participantRole($resource_role)]
            ));
        }
      }
    }
  }

  public function deleteResourceAssignment(PostDelete $event) {
    if ($event->object instanceof CRM_Resource_BAO_ResourceAssignment) {
      // TODO: Avoid infinite loops between post hooks for ResourceAssignment
      //       and Participant entities.

      $resource_role = Utils::getResourceRole();
      [$resource, $resource_demand] = self::getResourceAssignmentContext($event->object);
      if (
        $resource['entity_table'] == 'civicrm_contact'
        && $resource_demand['entity_table'] == 'civicrm_event'
      ) {
        $participants = Participant::get(FALSE)
          ->addWhere('contact_id', '=', $resource['entity_id'])
          ->addWhere('event_id', '=', $resource_demand['entity_id'])
          ->addWhere('role_id', 'LIKE', '%' . implode(\CRM_Core_DAO::VALUE_SEPARATOR, [$resource_role]) . '%')
          ->execute();
        switch ($participants->count()) {
          case 0:
            // No participant object exists, do not create one.
            break;
          case 1:
            // Update existing Participant with default negative participant status.
            $participant = $participants->single();
            Participant::update(FALSE)
              ->addWhere('id', '=', $participant['id'])
              ->addValue('status_id', Utils::getDefaultParticipantStatus('negative'))
              ->execute();
            break;
          default:
            throw new \Exception(E::ts(
              'More than one participant found with role %1.',
              [1 => \CRM_Event_PseudoConstant::participantRole($resource_role)]
            ));
        }
      }
    }
  }

  public static function getResourceAssignmentContext(CRM_Resource_BAO_ResourceAssignment $resource_assignment) {
    $resource = Resource::get(FALSE)
      ->addWhere('id', '=', $resource_assignment->resource_id)
      ->execute()
      ->single();
    $resource_demand = ResourceDemand::get(FALSE)
      ->addWhere('id', '=', $resource_assignment->resource_demand_id)
      ->execute()
      ->single();

    return [$resource, $resource_demand];
  }

}
