<?php

namespace App\Repository\Profile;

use App\Models\User;
use App\Models\UserProfile;

class ProfileRepository implements ProfileInterface
{

    /**
     * Ger User Profile
     * @param mixed $user
     *
     * @return mixed $user
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public static function getProfile($userProfile){

        $userProfile = $userProfile->load('primaryPhoto', 'profile', 'interests', 'photos');

        return $userProfile;

    }

    /**
     * Update User Profile
     * @param mixed $userId
     * @param mixed $requestArray
     *
     * @return 0
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public static function updateProfile($userId, $requestArray){

        $user = User::with('profile')->find($userId);
        $profile = UserProfile::firstOrCreate([
            'user_id' => $userId,
        ]);

        // Save only fillable values
        $profileFillables = $profile->getFillable();
        $userFillables = $user->getFillable();
        $profile->fill(
            array_filter($requestArray, function ($key) use ($profileFillables) {
                return in_array($key, $profileFillables);
            }, ARRAY_FILTER_USE_KEY)
        )->save();

        if (array_key_exists('first_name', $requestArray) OR
            array_key_exists('last_name', $requestArray)  OR
            array_key_exists('birthdate', $requestArray)  OR
            array_key_exists('zodiac_sign', $requestArray) OR
            array_key_exists('primary_photo', $requestArray)
        ) {
            if (array_key_exists('primary_photo', $requestArray)) {
                $requestArray['image'] = $requestArray['primary_photo'];
            }
            $user->fill($requestArray,   array_filter($requestArray, function ($key) use ($userFillables) {
                return in_array($key, $userFillables);
            }, ARRAY_FILTER_USE_KEY))->save();
        }

        // Sync User Photos Pivot Table
        if (array_key_exists('photos', $requestArray) ) {
            $user->assignMedia($requestArray['photos']);
        }
        // Sync User Interest Pivot Table
        if (array_key_exists('interests', $requestArray) ) {
            $user->assignInterest($requestArray['interests']);
        }

    }

}
