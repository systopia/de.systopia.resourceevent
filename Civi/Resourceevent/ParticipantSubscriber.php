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

use Civi\Core\DAO\Event\PostDelete;
use Civi\Core\DAO\Event\PostUpdate;
use CRM_Event_BAO_Participant;
use CRM_Resourceevent_ExtensionUtil as E;

class ParticipantSubscriber implements \Symfony\Component\EventDispatcher\EventSubscriberInterface {

  /**
   * @inheritDoc
   */
  public static function getSubscribedEvents() {
    return [
      'civi.dao.postInsert' => 'insertUpdateParticipant',
      'civi.dao.postUpdate' => 'insertUpdateParticipant',
      'civi.dao.postDelete' => 'deleteParticipant',
    ];
  }

  public function insertUpdateParticipant(PostUpdate $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = $event->object;
      if (in_array(Utils::getResourceRole(), \CRM_Utils_Array::explodePadded($participant->role_id))) {
        // TODO: Depending on participant status:
        //       - positive: create/update ResourceAssignment
        //       - negative: delete ResourceAssignment
      }
    }
  }

  public function deleteParticipant(PostDelete $event) {
    if ($event->object instanceof CRM_Event_BAO_Participant) {
      $participant = $event->object;
      if (in_array(Utils::getResourceRole(), \CRM_Utils_Array::explodePadded($participant->role_id))) {
        // TODO: Delete ResourceAssignment
      }
    }
  }

}
