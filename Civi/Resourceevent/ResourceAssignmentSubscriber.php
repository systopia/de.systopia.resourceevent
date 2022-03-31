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

  public function insertUpdateResourceAssignment(PostUpdate $event) {
    if ($event->object instanceof CRM_Resource_BAO_ResourceAssignment) {
      // TODO: Create/update Participant with values:
      //       - resource demand ID in custom field "resource_demand"
      //       - contact ID from resource
      //       - default positive participant status
      //       - participant role "human_resource"
    }
  }

  public function deleteResourceAssignment(PostDelete $event) {
    if ($event->object instanceof CRM_Resource_BAO_ResourceAssignment) {
      // TODO: Create/update Participant with values (or update if exists only?):
      //       - resource demand ID in custom field "resource_demand"
      //       - contact ID from resource
      //       - default negative participant status
      //       - participant role "human_resource"
    }
  }

}
