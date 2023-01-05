<?php

namespace App\Repository\Steps;

use App\Enums\StepReturn;
use App\Models\UserPhoto;
use App\Models\UserInterest;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StepsRepository implements StepsInterface
{
    /**
     * @param int $userid
     * @param int $userStatus
     *
     * @return [type]
     */
    public static function getStepRedirection(
        int $userId,
        int $userStatus
    ) {
        $step = '0';
        if ($userStatus == 1) {

            /***
             * We will check if user already set username and password
             * Then if not we return int 4 means that they need to
             */
            $checkIfUserNameOrPasswordFillIn = User::whereId($userId)
                ->first();

            if (
                empty($checkIfUserNameOrPasswordFillIn->user_name)
                or empty($checkIfUserNameOrPasswordFillIn->password)
            ) {
                // Return response what is not being done
                return StepReturn::USERNAME_PASSWORD_NOT_SET;
            }


            /*
            * Check if user exist in interest_to_users table
            * then if not it will return message to tell the FE to redirect to
            * select interest OR registration STEP 1
            */
            $checkIfExistInUserInterest = UserInterest::where('user_id', $userId)
                ->first();
            if (!$checkIfExistInUserInterest) {
                // Return message to tell that needs to add interest
                return StepReturn::USER_INTEREST_NOT_SET;
            }
            // END

            /*
            * Check if user exist in user_bios table
            * then if not it will return message to tell the FE to redirect to
            * select income_level, ethnicity, location, smoke, drink, martial status,
            * have children, educational_level OR registration STEP 2
            */

            $userProfile = UserProfile::query()->where('user_id', $userId);

            $checkIfExistInUserProfileStep2 = (clone $userProfile)
                ->where(function($query){
                    $query->whereNull('income_level')
                        ->orWhereNull('ethnicity')
                        ->orWhereNull('gender')
                        ->orWhereNull('city');
                })
                ->first();

            if ($checkIfExistInUserProfileStep2) {
                // Return message to tell that needs to user profile
                return StepReturn::USER_PROFILE_NOT_SET_STEP_2;
            }

            /*
            * Check if user exist in user_profile table
            * then if not it will return message to tell the FE to redirect to
            * input what_type_of_friend_are_you_looking_for, about_me, identify_events_activities OR registration STEP 2
            */
            $checkIfExistInUserProfileStep3 = (clone $userProfile)
                ->where(function($query){
                    $query->whereNull('what_type_of_friend_are_you_looking_for')
                        ->orWhereNull('about_me')
                        ->orWhereNull('identify_events_activities');
                })->first();

            if ($checkIfExistInUserProfileStep3) {
                // Return message to tell that needs to add interest
                return StepReturn::USER_PROFILE_NOT_SET_STEP_3;
            }

            /**
             * Check if there's a user photo uploaded
             */
            $step4Data = (clone $userProfile)->first();
            $userPhoto = User::with('photos')->where('id',$userId)->first();
            if ($userPhoto->photos->count() === 0 && $step4Data->is_step4_skipped === 0){
                return StepReturn::USER_PHOTO_NO_UPLOADED_STEP_4;
            }

            return StepReturn::USER_PREFERENCE_NOT_SET;
        }
    }

    /**
     * @param int $userId
     * @param int $interestId
     *
     * @return [type]
     */
    public static function userInterest(
        int $userId,
        int $interestId
    ) {
        $checkIfExists = UserInterest::where('user_id', $userId)
            ->where('interest_id', $interestId)
            ->first();

        if ($checkIfExists) {
            return true;
        }

        UserInterest::create([
            'user_id' => $userId,
            'interest_id' => $interestId
        ]);

        return true;
    }

    /**
     * @param int $userId
     * @param int $interestId
     *
     * @return [type]
     */
    public static function removeUserInterest(
        int $userId,
        int $interestId
    ) {

        UserInterest::where('interest_id', $interestId)
            ->where('user_id', $userId)
            ->delete();

        return true;
    }

    /**
     * @param Request $request
     * @param int $userId
     *
     * @return [type]
     */
    public static function moreAboutYourself(
        array $request,
        int $userId
    ) {

        $array_form = self::userProfileStep3($request, $userId);

        UserProfile::where('user_id', $userId)
            ->update($array_form);

        return true;
    }

    /**
     * @param int $userId
     * @param int $mediaId
     *
     * @return [type]
     */
    public static function createAdditionalPhoto(
        int $userId,
        int $mediaId
    ) {

        UserPhoto::create([
            'user_id' => $userId,
            'media_id' => $mediaId
        ]);

        return true;
    }

    /**
     * @param Request $request
     * @param int $userId
     *
     * @return [type]
     */
    public static function userPreference(
        array $request,
        int $userId
    ) {
        $data = self::userPreferenceForm($request, $userId);
        $checkIfExists = UserPreference::where('user_id', $userId);

        User::whereId($userId)
            ->update([
                'status' => 2
            ]);

        if (!$checkIfExists->first()) {
            //Create new record
            UserPreference::create($data);

            return Response::HTTP_CREATED;
        } else {
            //Update record
            UserPreference::where('user_id', $userId)
                ->update($data);

            return Response::HTTP_OK;
        }
    }

    public static function userProfileForm($request, $userId)
    {
        $profile = UserProfile::where('user_id', $userId)->first();

        $data = [];
        $data['user_id'] = $userId;
        if (isset($request['street_address'])) {
            $data['street_address'] = $request['street_address'] ? $request['street_address'] : $profile->street_address;
        }
        if (isset($request['city'])) {
            $data['city'] = $request['city'] ? $request['city'] : $profile->city;
        }
        if (isset($request['state'])) {
            $data['state'] = $request['state'] ? $request['state'] : $profile->state;
        }
        if (isset($request['zip_code'])) {
            $data['zip_code'] = $request['zip_code'] ? $request['zip_code'] : $profile->zip_code;
        }
        if (isset($request['country'])) {
            $data['country'] = $request['country'] ? $request['country'] : $profile->country;
        }

        if (isset($request['latitude'])) {
            $data['latitude'] = $request['latitude'];
        } else {
            $data['latitude'] = $profile ? $profile->latitude : 0;
        }

        if (isset($request['longitude'])) {
            $data['longitude'] = $request['longitude'];
        } else {
            $data['longitude'] = $profile ? $profile->longitude : 0;
        }

        if (isset($request['are_you_smoker'])) {
            $data['are_you_smoker'] = $request['are_you_smoker'] ? $request['are_you_smoker'] : $profile->are_you_smoker;
        }
        if (isset($request['are_you_drinker'])) {
            $data['are_you_drinker'] = $request['are_you_drinker'] ? $request['are_you_drinker'] : $profile->are_you_drinker;
        }
        if (isset($request['relationship_status'])) {
            $data['relationship_status'] = $request['relationship_status'] ? $request['relationship_status'] : $profile->relationship_status;
        }
        if (isset($request['any_children'])) {
            $data['any_children'] = $request['any_children'] ? $request['any_children'] : $profile->any_children;
        }
        if (isset($request['education_level'])) {
            $data['education_level'] = $request['education_level'] ? $request['education_level'] : $profile->education_level;
        }
        if (isset($request['income_level'])) {
            $data['income_level'] = $request['income_level'] ? $request['income_level'] : $profile->income_level;
        }
        if (isset($request['ethnicity'])) {
            $data['ethnicity'] = $request['ethnicity'] ? $request['ethnicity'] : $profile->ethnicity;
        }
        if (isset($request['gender'])) {
            $data['gender'] = $request['gender'] ? $request['gender'] : $profile->gender;
        }
        if (isset($request['body_type'])) {
            $data['body_type'] = $request['body_type'] ? $request['body_type'] : $profile->body_type;
        }
        if (isset($request['height'])) {
            $data['height'] = $request['height'] ? $request['height'] : $profile->height;
        }

        return $data;
    }

    public static function userProfileStep3($request, $userId)
    {
        $profile = UserProfile::where('user_id', $userId)->first();
        $data = [];
        if (isset($request['what_type_of_friend_are_you_looking_for'])) {
            $data['what_type_of_friend_are_you_looking_for'] = $request['what_type_of_friend_are_you_looking_for'] ? $request['what_type_of_friend_are_you_looking_for'] : $profile->what_type_of_friend_are_you_looking_for;
        }
        if (isset($request['about_me'])) {
            $data['about_me'] = $request['about_me'] ? $request['about_me'] : $profile->about_me;
        }
        if (isset($request['identify_events_activities'])) {
            $data['identify_events_activities'] = $request['identify_events_activities'] ? $request['identify_events_activities'] : $profile->about_me;
        }

        return $data;
    }

    public static function userPreferenceForm($request, $userId)
    {
        $userPreference = UserPreference::where('user_id', $userId)->first();
        $data = [];
        $data['user_id'] = $userId;
        if (isset($request['interest_id'])) {
            $data['interest_id'] = $request['interest_id'] ? $request['interest_id'] : $userPreference->interest_id;
        }
        if (isset($request['income_level'])) {
            $data['income_level'] = $request['income_level'] ? $request['income_level'] : $userPreference->income_level;
        }
        if (isset($request['ethnicity'])) {
            $data['ethnicity'] = $request['ethnicity'] ? $request['ethnicity'] : $userPreference->ethnicity;
        }
        if (isset($request['street_address'])) {
            $data['street_address'] = $request['street_address'] ? $request['street_address'] : $userPreference->street_address;
        }
        if (isset($request['city'])) {
            $data['city'] = $request['city'] ? $request['city'] : $userPreference->city;
        }
        if (isset($request['state'])) {
            $data['state'] = $request['state'] ? $request['state'] : $userPreference->state;
        }
        if (isset($request['zip_code'])) {
            $data['zip_code'] = $request['zip_code'] ? $request['zip_code'] : $userPreference->zip_code;
        }
        if (isset($request['country'])) {
            $data['country'] = $request['country'] ? $request['country'] : $userPreference->country;
        }

        if (isset($request['latitude'])) {
            $data['latitude'] = $request['latitude'] ? $request['latitude'] : $userPreference->latitude;
        } else {
            $data['latitude'] = $userPreference ? $userPreference['latitude'] : 0;
        }

        if (isset($request['longitude'])) {
            $data['longitude'] = $request['longitude'];
        } else {
            $data['longitude'] = $userPreference ? $userPreference['longitude'] : 0;
        }

        if (isset($request['gender'])) {
            $data['gender'] = $request['gender'] ? $request['gender'] : $userPreference->gender;
        }
        if (isset($request['age_from'])) {
            $data['age_from'] = $request['age_from'] ? $request['age_from'] : $userPreference->age_from;
        }
        if (isset($request['age_to'])) {
            $data['age_to'] = $request['age_to'] ? $request['age_to'] : $userPreference->age_to;
        }
        if (isset($request['zodiac_sign'])) {
            $data['zodiac_sign'] = $request['zodiac_sign'] ? $request['zodiac_sign'] : $userPreference->zodiac_sign;
        }
        if (isset($request['are_you_smoker'])) {
            $data['are_you_smoker'] = $request['are_you_smoker'] ? $request['are_you_smoker'] : $userPreference->are_you_smoker;
        }
        if (isset($request['are_you_drinker'])) {
            $data['are_you_drinker'] = $request['are_you_drinker'] ? $request['are_you_drinker'] : $userPreference->are_you_drinker;
        }
        if (isset($request['any_children'])) {
            $data['any_children'] = $request['any_children'] ? $request['any_children'] : $userPreference->any_children;
        }
        if (isset($request['education_level'])) {
            $data['education_level'] = $request['education_level'] ? $request['education_level'] : $userPreference->education_level;
        }
        if (isset($request['relationship_status'])) {
            $data['relationship_status'] = $request['relationship_status'] ? $request['relationship_status'] : $userPreference->relationship_status;
        }
        if (isset($request['body_type'])) {
            $data['body_type'] = $request['body_type'] ? $request['body_type'] : $userPreference->body_type;
        }
        if (isset($request['height_from'])) {
            $data['height_from'] = $request['height_from'] ? $request['height_from'] : $userPreference->height_from;
        }
        if (isset($request['height_to'])) {
            $data['height_to'] = $request['height_to'] ? $request['height_to'] : $userPreference->height_to;
        }

        return $data;
    }
}
