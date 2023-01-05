<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Forms\HomeForm;
use App\Http\Resources\UserBioResource;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function isShowWelcomePopup()
    {
        return [
            'success' => true,
            'data' => HomeForm::isShowWelcomePopup(),
        ];
    }

    public function dontShowWelcomePopup()
    {
        $success = HomeForm::dontShowWelcomePopup();
        return [
            'success' => $success,
            'data' => new UserBioResource(
                UserProfile::where('user_id', Auth::user()->id)->first())
        ];
    }
}
