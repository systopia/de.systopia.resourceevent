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

/**
 * Search result action for inviting contacts as resources for an event. This
 * utilises the action of the Event Invitation extension, locking the
 * participant role to the resource_role and triggering the
 * ParticipantSubscriber for creating a resource assignment.
 */
class CRM_Resourceevent_Form_Task_InviteResource extends CRM_Eventinvitation_Form_Task_ContactSearch {

  public function buildQuickForm() {
    parent::buildQuickForm();

    // TODO: Remove the element and add it as a value element without a field.
    $this->removeElement(self::PARTICIPANT_ROLES_ELEMENT_NAME);
  }

}
