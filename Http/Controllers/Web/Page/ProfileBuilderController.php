<?php

namespace App\Http\Controllers\Web\Page;

use Auth;
use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediasResource;
use App\Http\Resources\UserPreferenceResource;
use App\Models\UserPhoto;
use App\Models\UserPreference;
use App\Models\UserProfile;

class ProfileBuilderController extends Controller
{
    public function step1()
    {
        return Inertia::render('ProfileBuilder/Step1', ['title' => '- Profile Step 1']);
    }

    public function step2()
    {
        $profile = UserProfile::where('user_id', '=', Auth::id())
            ->first();

        return Inertia::render('ProfileBuilder/Step2', [
            'profile' => $profile,
            'title' => '- Profile Step 2'
        ]);
    }

    public function step3()
    {
        $descriptions = UserProfile::where('user_id', '=', Auth::id())
            ->select('id', 'what_type_of_friend_are_you_looking_for', 'about_me', 'identify_events_activities')
            ->first();

        return Inertia::render('ProfileBuilder/Step3', [
            'descriptions' => $descriptions,
            'title' => '- Profile Step 3'
        ]);
    }

    public function step4()
    {
        $photos = UserPhoto::where('user_id', '=', Auth::id())
            ->with('media')
            ->get()
            ->pluck('media');

        return Inertia::render('ProfileBuilder/Step4', [
            'photos' => new MediasResource($photos),
            'title' => '- Profile Step 4'
        ]);
    }

    public function step5()
    {
        $preferences = UserPreference::where('user_id', '=', Auth::id())
            ->first();

        $redirectTo = authUser()->post_registration_redirect;

        return Inertia::render('ProfileBuilder/Step5', [
            'preferences' => $preferences ? (new UserPreferenceResource($preferences)) : null,
            'redirectTo' => $redirectTo,
            'title' => '- Profile Step 5'
        ]);
    }
}
