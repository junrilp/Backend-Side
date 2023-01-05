<?php

namespace App\Repository\Profile;

interface ProfileInterface
{


    public static function getProfile($user);

    public static function updateProfile(int $userId,array $requestArray);

}
