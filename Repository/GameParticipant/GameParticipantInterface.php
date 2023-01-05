<?php

namespace App\Repository\GameParticipant;

use App\Models\GameParticipant;

interface GameParticipantInterface
{
    public static function invite($data);

    public static function delete(GameParticipant $gameParticipant);

    public static function update(GameParticipant $gameParticipant, $data);

    public static function updateStatus(GameParticipant $gameParticipant, $data);

    public static function getAll($data);
}
