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

use Civi\Api4\CustomValue;
use Civi\Api4\Participant;
use Civi\Api4\Resource;
use Civi\Api4\ResourceAssignment;
use Civi\Api4\ResourceDemand;
use Civi\Core\DAO\Event\PostDelete;
use Civi\Core\DAO\Event\PostUpdate;
use Civi\Core\DAO\Event\PreUpdate;
use Civi\FormProcessor\Type\ParticipantStatusType;
use CRM_Event_BAO_Participant;
use CRM_Resourceevent_ExtensionUtil as E;
use Stripe\Util\Util;

class ParticipantSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      'civi.dao.preUpdate' => 'preUpdateParticipant',
      'civi.dao.postInsert' => 'insertUpdateParticipant',
      'civi.dao.postUpdate' => 'insertUpdateParticipant',
      'civi.dao.postDelete' => 'deleteParticipant',
    ];
  }

  public function preUpdateParticipant(PreUpdate $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = Participant::get(FALSE)
        ->addWhere('id', '=', $event->object->id)
        ->execute()
        ->single();
      $resource_role = Utils::getResourceRole();
      if (
        in_array($resource_role, $participant['role_id'])
        && !in_array($resource_role, explode(\CRM_Core_DAO::VALUE_SEPARATOR, $event->object->role_id))
      ) {
        // About to withdraw the resource role from the participant, mark
        // participant as affected.
        \Civi::$statics[__CLASS__]['affected_participants'][] = $participant['id'];
      }
    }
  }

  public function insertUpdateParticipant(PostUpdate $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = $event->object;
      // TODO: Avoid infinite loops between post hooks for ResourceAssignment
      //       and Participant entities.

      // Delete resource assignment for participants with the resource role or
      // those being withdrawn the resource role.
      if (
        (
          in_array(Utils::getResourceRole(), explode(\CRM_Core_DAO::VALUE_SEPARATOR, $participant->role_id))
          && \CRM_Event_BAO_ParticipantStatusType::getIsValidStatusForClass($participant->status_id, 'Negative')
        )
        || in_array($participant->id, \Civi::$statics[__CLASS__]['affected_participants'])
      ) {
        self::deleteResourceAssignment($participant);
      }
    }
  }

  public function deleteParticipant(PostDelete $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = $event->object;
      if (in_array(Utils::getResourceRole(), explode(\CRM_Core_DAO::VALUE_SEPARATOR, $participant->role_id))) {
        // TODO: Avoid infinite loops between post hooks for ResourceAssignment
        //       and Participant entities.

        // Delete ResourceAssignment
        self::deleteResourceAssignment($participant);
      }
    }
  }

  public static function deleteResourceAssignment(CRM_Event_BAO_Participant $participant) {
    $resource_assignment = Utils::getResourceAssignmentForParticipant($participant->id);
    ResourceAssignment::delete(FALSE)
      ->addWhere('id', '=', $resource_assignment['id'])
      ->execute();
  }

}