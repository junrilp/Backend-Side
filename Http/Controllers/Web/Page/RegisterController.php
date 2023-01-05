<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Forms\RegistrationForm;
use Illuminate\Support\Facades\Cache;
use App\Models\SmsVerification;
use Log;

class RegisterController extends Controller
{
    public function index()
    {
        return Inertia::render('Register', ['title' => '- Registration']);
    }

    private function getUserDetails(Request $request)
    {
        $userDetails = [];

        if ($request->has('mobile_number') && $request->has('pin')) {
            try {
                $smsVerification = SmsVerification::where('mobile_number', str_replace('-', '', $request->mobile_number))
                    ->where('pin', $request->pin)
                    ->has('user')
                    ->firstOrFail();

                $userDetails = [
                    'userId' => $smsVerification->user->id,
                    'firstName' => $smsVerification->user->first_name,
                    'lastName' => $smsVerification->user->last_name, 
                    'emailAddress' => $smsVerification->user->email, 
                    'birthdate' => $smsVerification->user->birth_date,
                    'zodiacSignId' => $smsVerification->user->zodiac_sign,
                    'primaryPhotoId' => $smsVerification->user->image,
                ];
            } catch (\Exception $e) {
                Log::info('Invalid mobile phone and pin. '. $e->getMessage());
            }
        }

        return $userDetails;
    }

    public function perfectFriend(Request $request, RegistrationForm $registrationForm)
    {
        return Inertia::render('Register/PerfectFriend', [
            'temporaryUsername' => $registrationForm::generateUserName(),
            'mobileNumber' => Cache::get('mobile_number'),
            'title' => '- Registration | Perfect Friend'
        ] + ($this->getUserDetails($request)));
    }

    public function perfectFriendConfirm()
    {
        return Inertia::render('Register/PerfectFriend/Confirm');
    }

    public function perfectFriendInfluencer(Request $request, RegistrationForm $registrationForm)
    {
        return Inertia::render('Register/PerfectFriendInfluencer', [
            'temporaryUsername' => $registrationForm::generateUserName(),
            'mobileNumber' => Cache::get('mobile_number'),
            'title' => '- Registration | Perfect Friend Influencer'
        ] + ($this->getUserDetails($request)));
    }

    public function perfectFriendInfluencerConfirm()
    {
        return Inertia::render('Register/PerfectFriendInfluencer/Confirm');
    }

    public function invalidToken()
    {
        return Inertia::render('InvalidToken');
    }
}
