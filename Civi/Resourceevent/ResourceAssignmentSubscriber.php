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
      $resource_assignment = $event->object;
      $resource = Resource::get(FALSE)
        ->addWhere('id', '=', $resource_assignment->resource_id)
        ->execute()
        ->single();
      $resource_demand = ResourceDemand::get(FALSE)
        ->addWhere('id', '=', $resource_assignment->resource_demand_id)
        ->execute()
        ->single();
      if (
        $resource['entity_table'] == 'civicrm_contact'
        && $resource_demand['entity_table'] == 'civicrm_event'
      ) {
        $participants = Participant::get(FALSE)
          ->addWhere('contact_id', '=', $resource['entity_id'])
          ->addWhere('event_id', '=', $resource_demand['entity_id'])
          ->addWhere('role_id', 'LIKE', '%' . implode(\CRM_Core_DAO::VALUE_SEPARATOR, [$resource_role]) . '%')
          ->execute();
        $values = [
          'contact_id' => $resource['entity_id'],
          'event_id' => $resource_demand['entity_id'],
          'role_id' => [$resource_role],
          'custom_' . Utils::getDemandCustomFieldId() => $resource_demand['id'],
          'status_id' => Utils::getDefaultParticipantStatus('positive'),
        ];
        switch ($participants->count()) {
          case 0:
            Participant::create(FALSE)
              ->setValues($values)
              ->execute();
            break;
          case 1:
            $participant = $participants->single();
            // Keep current roles.
            $values['role_id'] = $participant['role_id'];
            Participant::update(FALSE)
              ->addWhere('id', '=', $participant['id'])
              ->setValues($values)
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
      // TODO: Create/update Participant with values (or update if exists only?):
      //       - resource demand ID in custom field "resource_demand"
      //       - contact ID from resource
      //       - default negative participant status
      //       - participant role "human_resource"
    }
  }

}
