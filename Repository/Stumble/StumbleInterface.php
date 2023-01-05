<?php

namespace App\Repository\Stumble;

use App\Models\Stumble;
use App\Models\User;

interface StumbleInterface
{

    public static function postStumble(User $user, $long, $lat);

    public static function postStumbled($stumbles, $long, $lat);

    public static function deleteStumble(Stumble $user);

    public static function getNearbyStumbles($lat, $long, $distance);

    public static function getStumbled(User $user);

    public static function getNearbyStumblesByKeyword($lat, $long, $distance, $keyword);
}
