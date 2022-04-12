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
      'civi.dao.preInsert' => 'preInsertUpdateParticipant',
      'civi.dao.preUpdate' => 'preInsertUpdateParticipant',
      'civi.dao.postInsert' => 'insertUpdateParticipant',
      'civi.dao.postUpdate' => 'insertUpdateParticipant',
      'civi.dao.postDelete' => 'deleteParticipant',
    ];
  }

  /**
   * Handles pre-insert and -update events for participant objects.
   *
   * @param PreUpdate $event
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function preInsertUpdateParticipant(PreUpdate $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = (array) $event->object;
      try {
        $current_participant = Participant::get(FALSE)
          ->addWhere('id', '=', $participant['id'])
          ->execute()
          ->single();
      }
      catch (\Exception $exception) {
        // No existing participant, this is an insert.
      }

      if (
        isset($current_participant)
        && self::participantHasResourceRole($current_participant)
        && !self::participantHasResourceRole($participant)
      ) {
        // About to withdraw the resource role from the participant, mark
        // participant as affected.
        self::participantAffected($participant['id'], TRUE);
      }

      if (
        self::participantHasResourceRole($participant)
        && !self::participantHasResourceDemand($participant)
      ) {
        // About to be assigned the resource role without a resource demand,
        // abort the action.
        throw new \Exception(E::ts('Could not add participant with resource role without a resource demand.'));
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
      $participant = (array) $event->object;
      // TODO: Avoid infinite loops between post hooks for ResourceAssignment
      //       and Participant entities.

      if (
        (
          self::participantHasResourceRole($participant)
          && \CRM_Event_BAO_ParticipantStatusType::getIsValidStatusForClass($participant['status_id'], 'Negative')
        )
        || self::participantAffected($participant['id'])
      ) {
        // Delete resource assignment for participants with a negative status
        // having or being withdrawn the resource role.
        self::deleteResourceAssignment($participant);
      }
      elseif (
        self::participantHasResourceRole($participant)
        && \CRM_Event_BAO_ParticipantStatusType::getIsValidStatusForClass($participant['status_id'], 'Positive')
        && self::participantHasResourceDemand($participant)
      ) {
        // Create resource assignment for participants with a positive status
        // having the resource role and a resource demand stored.
        self::createResourceAssignment($participant);
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
      $participant = (array) $event->object;
      if (self::participantHasResourceRole($participant)) {
        // TODO: Avoid infinite loops between post hooks for ResourceAssignment
        //       and Participant entities.

        // Delete ResourceAssignment
        self::deleteResourceAssignment($participant);
      }
    }
  }

  /**
   * Creates a resource assignment for a given participant.
   *
   * @param array $participant
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function createResourceAssignment(array $participant) {
    if (!Utils::getResourceAssignmentForParticipant($participant['id'])) {
      $participant = Participant::get(FALSE)
        ->addSelect('resource_information.resource_demand')
        ->addWhere('id', '=', $participant['id'])
        ->execute()
        ->single();
      ResourceAssignment::create(FALSE)
        ->addValue('resource_id', Utils::getResourceForParticipant($participant['id']))
        ->addValue('resource_demand_id', $participant['resource_information.resource_demand'])
        ->execute();
    }
  }

  /**
   * Deletes a resource assignment for a given participant.
   *
   * @param array $participant
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public static function deleteResourceAssignment(array $participant) {
    $resource_assignment = Utils::getResourceAssignmentForParticipant($participant->id);
    ResourceAssignment::delete(FALSE)
      ->addWhere('id', '=', $resource_assignment['id'])
      ->execute();
  }

  /**
   * Checks whether a given participant is currently assigned the resource role.
   *
   * @param array $participant
   *
   * @return bool
   */
  public static function participantHasResourceRole(array $participant) {
    return in_array(
      Utils::getResourceRole(),
      is_array($participant['role_id']) ? $participant['role_id'] : explode(\CRM_Core_DAO::VALUE_SEPARATOR, $participant['role_id'])
    );
  }

  /**
   * Checks or sets a given participant being subject to change during an insert
   * or update. This utilises a static storage for handing affected participants
   * from pre event to post event implementations.
   *
   * @param $participant_id
   * @param $affected
   *
   * @return bool
   */
  public static function participantAffected($participant_id, $affected = NULL) {
    if (!is_array($affected_participants = &\Civi::$statics[__CLASS__]['affected_participants'])) {
      $affected_participants = [];
    }
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

  /**
   * Checks whether a given participant currently has a resource demand stored.
   *
   * @param array $participant
   *
   * @return bool
   */
  public static function participantHasResourceDemand(array $participant) {
    try {
      Participant::get(FALSE)
        ->addWhere('id', '=', $participant['id'])
        ->addWhere('resource_information.resource_demand', 'IS NOT EMPTY')
        ->execute()
        ->single();
      return TRUE;
    }
    catch (\Exception $exception) {
      return FALSE;
    }
  }

}
