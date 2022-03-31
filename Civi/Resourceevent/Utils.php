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

use Civi\Api4\OptionValue;
use CRM_Resourceevent_ExtensionUtil as E;

class Utils {

  public static function getResourceRole() {
    return OptionValue::get(FALSE)
      ->addSelect('value')
      ->addWhere('option_group_id.name', '=', 'participant_role')
      ->addWhere('name', '=', 'human_resource')
      ->execute()
      ->single()['value'];
  }

}
