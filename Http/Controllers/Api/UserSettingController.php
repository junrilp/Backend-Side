<?php

namespace App\Http\Controllers\Api;

use Auth;
use App\Models\User;
use App\Models\UserSetting;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\DefaultPageType;
use App\Models\RestrictedWord;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserSettingsResource;
use App\Http\Resources\UserBasicInfoResource;

class UserSettingController extends Controller
{
    use ApiResponser;
    /**
     * Update user account settings
     *
     * @param Request $request
     * @return App\Traits\ApiResponser::successResponse
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function userUpdateAccountSettings(Request $request){
        $userId = Auth::user()->id;
        UserSetting::updateOrCreate([
            'user_id' => $userId
        ],[
            'default_landing_page_type' => $request->browse_as_default,
            'show_welcome_msg' => $request->show_welcome_message,
            'show_mobile_browse_msg' => $request->show_mobile_browse_message ? $request->show_mobile_browse_message : 0,
            'show_mobile_events_msg' => $request->show_mobile_events_message ? $request->show_mobile_events_message : 0,
            'show_mobile_groups_msg' => $request->show_mobile_groups_message ? $request->show_mobile_groups_message : 0,
        ]);
        return $this->successResponse(null);
    }

    /**
     * Update the propt welcome message on the dashboard so it will not show
     * Can be use show or not show the welcome message
     *
     * @param Request $request
     * @return App\Traits\ApiResponser::successResponse
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function dontShowAgain(Request $request){
        $userId = Auth::user()->id;
        UserSetting::updateOrCreate([
            'user_id' => $userId
        ],[
            'show_welcome_msg' => $request->show_welcome_message
        ]);
        return $this->successResponse(null);
    }

    /**
     * Will retrieve user account settings
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getUserAccountSettings(){
        $userId = Auth::user()->id;
        $settings = UserSetting::where('user_id',$userId)->first();
        return $this->successResponse(new UserSettingsResource($settings));
    }

    /**
     * Will retrieve the available landing page as default page
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getLandingPageEnums(){
        $data = DefaultPageType::map();
        return $this->successResponse($data);
    }

    /**
     * Will retrieve the owner basic information
     *
     * @return UserBasicInfoResource
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getOwner()
    {
        return $this->successResponse(new UserBasicInfoResource(User::findOrFail(env('FIRST_FRIEND_ID'))));
    }

    public function getWordRestriction()
    {
        return $this->successResponse(RestrictedWord::select('id','label')->get());
    }

    /**
     * This will validate word validation from FE
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function checkWordRestriction(Request $request)
    {
        $check = checkWordRestriction($request->content);
        if ($check) {
            return $this->errorResponse($check, Response::HTTP_NOT_ACCEPTABLE);
        }
    }
}
