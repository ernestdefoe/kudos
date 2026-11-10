<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Everyone can read the wall (a forum closed to guests stays closed: Access), members give, moderators keep notes civil.
return Migration::addPermissions([
    'kudos.view' => Group::GUEST_ID,
    'kudos.give' => Group::MEMBER_ID,
    'kudos.moderate' => Group::MODERATOR_ID,
]);
