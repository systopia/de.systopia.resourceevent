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
use Civi\Api4\ResourceAssignment;
use Civi\Core\DAO\Event\PostDelete;
use Civi\Core\DAO\Event\PostUpdate;
use Civi\Core\DAO\Event\PreUpdate;
use CRM_Event_BAO_Participant;
use CRM_Resourceevent_ExtensionUtil as E;

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

  /**
   * Handles pre-update events for participant objects.
   *
   * @param \Civi\Core\DAO\Event\PreUpdate $event
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
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
        self::participantAffected($participant['id'], TRUE);
      }
    }
  }

  /**
   * Handles post-insert and -update events for participant objects.
   *
   * @param \Civi\Core\DAO\Event\PostUpdate $event
   *
   * @return void
   */
  public function insertUpdateParticipant(PostUpdate $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = $event->object;
      // TODO: Avoid infinite loops between post hooks for ResourceAssignment
      //       and Participant entities.

      // Delete resource assignment for participants having or being withdrawn
      // the resource role.
      if (
        (
          self::participantHasResourceRole($participant)
          && \CRM_Event_BAO_ParticipantStatusType::getIsValidStatusForClass($participant->status_id, 'Negative')
        )
        || self::participantAffected($participant->id)
      ) {
        self::deleteResourceAssignment($participant);
      }
    }
  }

  /**
   * Handles post-delete events for participant objects.
   *
   * @param \Civi\Core\DAO\Event\PostDelete $event
   *
   * @return void
   */
  public function deleteParticipant(PostDelete $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = $event->object;
      if (self::participantHasResourceRole($participant)) {
        // TODO: Avoid infinite loops between post hooks for ResourceAssignment
        //       and Participant entities.

        // Delete ResourceAssignment
        self::deleteResourceAssignment($participant);
      }
    }
  }

  /**
   * Deletes a resource assignment for a given participant.
   *
   * @param \CRM_Event_BAO_Participant $participant
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function deleteResourceAssignment(CRM_Event_BAO_Participant $participant) {
    $resource_assignment = Utils::getResourceAssignmentForParticipant($participant->id);
    ResourceAssignment::delete(FALSE)
      ->addWhere('id', '=', $resource_assignment['id'])
      ->execute();
  }

  public static function participantHasResourceRole(CRM_Event_BAO_Participant $participant) {
    return in_array(
      Utils::getResourceRole(),
      explode(\CRM_Core_DAO::VALUE_SEPARATOR, $participant->role_id)
    );
  }

  public static function participantAffected($participant_id, $affected = NULL) {
    $affected_participants = &\Civi::$statics[__CLASS__]['affected_participants'];
    if (isset($affected)) {
      if ($affected) {
        $affected_participants[] = $participant_id;
      }
      else {
        unset($affected_participants[array_search($participant_id, $affected_participants)]);
      }
    }
    else {
      $affected = in_array($participant_id, $affected_participants);
    }
    return $affected;
  }

}
