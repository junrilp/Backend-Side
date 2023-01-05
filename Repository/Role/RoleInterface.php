<?php

namespace App\Repository\Role;

use App\Models\Event;

interface RoleInterface
{

    public static function assignUserRole(array $eventRoles = [], Event $event, int $loginUserId);

    public function canScanQr();

    public function getAbilities();

}
