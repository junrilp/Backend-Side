<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Resources\UserSettingsResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\UserSetting;
use Auth;

class UserSettingController extends Controller
{
    /**
     * Display the user account settings page and include data with it
     *
     * @return void
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index(){
        $userId = Auth::user()->id;
        $data   = UserSetting::where('user_id', $userId)->first();
        return Inertia::render('UserSetting/Account',['settings' => new UserSettingsResource($data), 
                                                         'title' => '- Settings Page']);
    }

    /**
     * Display notificaton page
     * Note: this is sample page for future changes
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function notification(){
        return Inertia::render('UserSetting/Notification', ['title' => '- Notification']);
    }
}
