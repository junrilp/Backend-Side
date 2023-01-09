<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Repository\Profile\ProfileRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Requests\EditProfileRequest;
use Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    use ApiResponser;

    /**
     * Display a listing of the resource.
     * @param User $user
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index(User $user)
    {

        $user = ProfileRepository::getProfile($user);

        return $this->successResponse(new ProfileResource($user));

    }

    public function getProfile(Request $request)
    {

        $username = User::whereId($request->user)->pluck('user_name')[0];

        try {
            $user = User::where('user_name', $username)->firstOrFail();

            $userProfile = ProfileRepository::getProfile($user);

            return $this->successResponse(new ProfileResource($userProfile));

        } catch (\Exception $e) {
            \Log::alert($e);
            return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
        }
    }




    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @param EditProfileRequest $request
     * @return \Illuminate\Http\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function show($id, Request $request)
    {
        try {
            $restrictedWord = checkMultipleColumnForRestrictedWord($request->except('photos','email','latitude','longitude'));
            if ($restrictedWord) {
                return $this->errorResponse($restrictedWord , Response::HTTP_NOT_ACCEPTABLE);
            }

            ProfileRepository::updateProfile($id, $request->all());

            $user = User::with('primaryPhoto', 'profile', 'interests', 'photos')->find($id);

            //return only the updated values
            return $this->successResponse(
                (new ProfileResource($user))
                ->returnFields(
                    array_keys($request->all())
                )
            );

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Unable to update Profile', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
