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
use Civi\Core\DAO\Event\PreUpdate;
use CRM_Resourceevent_ExtensionUtil as E;

class ParticipantSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      '&hook_civicrm_pre' => 'preParticipant',
      '&hook_civicrm_post' => 'delegatePostCallback',
      '&hook_civicrm_buildForm' => 'buildParticipantForm',
      '&hook_civicrm_validateForm' => 'validateParticipantForm',
    ];
  }

  public function delegatePostCallback($op, $objectName, $objectId, &$objectRef) {
    if ($objectName == 'Participant' && in_array($op, ['create', 'edit'])) {
      // If we're inside a transaction, register a callback.
      if (\CRM_Core_Transaction::isActive()) {
        \CRM_Core_Transaction::addCallback(
          \CRM_Core_Transaction::PHASE_POST_COMMIT,
          [self::class, 'insertUpdateParticipant'],
          [$op, $objectName, $objectId, &$objectRef]
        );
      }
      // If the transaction is already finished, call the function directly.
      else {
        self::insertUpdateParticipant($op, $objectName, $objectId, $objectRef);
      }
    }
  }

  /**
   * Handles pre-insert and -update events for participant objects.
   *
   * @return void
   * @throws \API_Exception
   * @throws \Civi\API\Exception\UnauthorizedException
   */
  public function preParticipant($op, $objectName, $id, &$params) {
    if ($objectName == 'Participant') {
      if (!empty($id)) {
        \Civi::$statics['resourceevent']['editing_participants'][] = $id;
        $current_participant = Participant::get(FALSE)
          ->addSelect('role_id', 'resource_information.resource_demand')
          ->addWhere('id', '=', $id)
          ->execute()
          ->single();
        $params += $current_participant;
      }
      if (in_array($op, ['create', 'edit'])) {
        if (
          isset($current_participant)
          && self::participantHasResourceRole($current_participant)
          && !self::participantHasResourceRole($params)
        ) {
          // About to withdraw the resource role from the participant, mark
          // participant as affected.
          self::participantAffected($id, TRUE);
        }

        if (
          self::participantHasResourceRole($params)
          && !self::participantHasResourceDemand($params)
        ) {
          // About to be assigned the resource role without a resource demand,
          // abort the action.
          throw new \Exception(E::ts('Could not add/edit participant with resource role without a resource demand.'));
        }
      }
      elseif ($op == 'delete') {
        // About to delete the participant, delete the resource assignment.
        self::deleteResourceAssignment($current_participant);
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
  public function insertUpdateParticipant($op, $objectName, $objectId, &$objectRef) {
    \Civi::$statics['resourceevent']['editing_participants'][] = $objectId;
    $participant = Participant::get(FALSE)
      ->addSelect('role_id', 'status_id', 'resource_information.resource_demand')
      ->addWhere('id', '=', $objectId)
      ->execute()
      ->single();

    if (
      (
        self::participantHasResourceRole($participant)
        && (
          !\CRM_Event_BAO_ParticipantStatusType::getIsValidStatusForClass($participant['status_id'], 'Positive')
        )
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

  /**
   * Handles hook_civicrm_buildForm events for participant forms.
   */
  public function buildParticipantForm($formName, \CRM_Core_Form &$form) {
    // Remove resource role from role selection fields on participant-related
    // forms, if it is not in their current values.
    $forms = [
      'CRM_Event_Form_Participant' => 'role_id',
      'CRM_Event_Form_Task_Register' => 'role_id',
      'CRM_Event_Form_ManageEvent_EventInfo' => 'default_role_id',
    ];
    if (array_key_exists($formName, $forms)) {
      try {
        $roles_element = &$form->getElement($forms[$formName]);
        if (!in_array(Utils::getResourceRole(), $roles_element->getValue())) {
          foreach ($roles_element->_options as $key => $option) {
            if ($option['attr']['value'] == Utils::getResourceRole()) {
              unset($roles_element->_options[$key]);
            }
          }
        }
      }
      catch (\Exception $exception) {
        // Element does not exist in form, this is most likely a delete action.
      }
    }
  }

  /**
   * Handles hook_civicrm_validateForm events for participant forms.
   */
  public function validateParticipantForm($formName, &$fields, &$files, &$form, &$errors) {
    // Do not allow selecting the resource role on participant-related forms.
    $forms = [
      'CRM_Event_Form_Participant' => 'role_id',
      'CRM_Event_Form_Task_Register' => 'role_id',
    ];
    if (array_key_exists($formName, $forms)) {
      try {
        $roles_element = &$form->getElement($forms[$formName]);
        if (!in_array(Utils::getResourceRole(), $roles_element->getValue())) {
          $role_field_value = $fields[$forms[$formName]];
          if (!empty($role_field_value) && !is_array($role_field_value)) {
            $fields[$forms[$formName]] = [$role_field_value];
          }
          if (in_array(Utils::getResourceRole(), $fields[$forms[$formName]])) {
            $errors[$forms[$formName]] = E::ts(
              'The CiviResource Event role is not allowed to be selected.'
            );
          }
        }
      }
      catch (\Exception $exception) {
        // Element does not exist in form, this is most likely a delete action.
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
        ->addValue('resource_id', Utils::getResourceForParticipant($participant['id'])['id'])
        ->addValue('resource_demand_id', $participant['resource_information.resource_demand'])
        ->addValue('status', \CRM_Resource_BAO_ResourceAssignment::STATUS_CONFIRMED)
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
    if ($resource_assignment = Utils::getResourceAssignmentForParticipant($participant['id'])) {
      ResourceAssignment::delete(FALSE)
        ->addWhere('id', '=', $resource_assignment['id'])
        ->execute();
    }
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
    if (!is_array($affected_participants = &\Civi::$statics['resourceevent']['affected_participants'])) {
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
    return !empty($participant['resource_information.resource_demand']);
  }

}
