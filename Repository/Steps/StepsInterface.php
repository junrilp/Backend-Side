<?php

namespace App\Repository\Steps;

use Illuminate\Http\Request;

interface StepsInterface
{
    public static function getStepRedirection(
        int $userid,
        int $userStatus
    );

    public static function userInterest(
        int $userId,
        int $interest_id
    );

    public static function removeUserInterest(
        int $userId,
        int $interestId
    );

    public static function moreAboutYourself(
        array $request,
        int $userId
    );

    public static function createAdditionalPhoto(
        int $userId,
        int $mediaId
    );

    public static function userPreference(
        array $request,
        int $userId
    );
}
