<?php

namespace App\Http\Controllers\Api;

use App\Forms\PerfectFriendForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserPreferenceRequest;
use App\Http\Resources\UserInterestResource;

use App\Models\Interest;

use App\Models\User;
use App\Models\UserInterest;
use App\Models\UserProfile;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class StepRegistrationController extends Controller
{
    private $userInterest;
    private $profile;
    private $perfectFriendForm;

    use ApiResponser;

    public function __construct(
        UserInterest $userInterest,
        UserProfile $profile,
        PerfectFriendForm $perfectFriendForm
    )
    {
        $this->userInterest = $userInterest;
        $this->profile = $profile;
        $this->perfectFriendForm = $perfectFriendForm;
    }

    /**
     * @param Request $request
     * Save Interest Step 1
     * This will serve as the post request for
     * interest to user
     * @return [type]
     */
    public function userInterest(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'interest_id'   => ['required'],
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }
            DB::beginTransaction();
            $data = $this->perfectFriendForm->userInterest($request);
            DB::commit();
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message'=>"Sorry, Duplicate interest with same user is not allowed"
                ], 422);
            }
            return response()->json([
                'success' => true,
                'data' => UserInterestResource::collection(
                            UserInterest::where('user_id', Auth::user()->id)->get()
                        )
            ], 201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    //Remove Interest Step 1
    public function removeUserInterest($id)
    {
        try {
            DB::beginTransaction();
            $data = $this->perfectFriendForm->removeUserInterest($id);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    //Save Step 2
    /*

    */
    /**
     * @param Request $request
     *
     * income level, ethnicity, localtion, are you smoker,
     * are you drinker, relationship status, any children, educational level
     * @return [type]
     */
    public function aboutYourself(Request $request)
    {
        try {

            $data = $this->perfectFriendForm->aboutYourself($request);

            return $data;
        }
        catch(\Exception $e) {

            throw $e;
        }
    }

    //Save Step 3
    /*
    * income level, ethnicity, localtion, are you smoker,
    * are you drinker, relationship status, any children, educational level
    */
    public function moreAboutYourself(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->perfectFriendForm->moreAboutYourself($request);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * @param UserPreferenceRequest $request
     *
     * @return [type]
     */
    public function userPreference(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->perfectFriendForm->userPreference($request);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }

    }

    /**
    * publish profile
    * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
    * @author Junril Pateño <junril090693@gmail.com>
    */
    public function publishProfile()
    {
        try {

            $published = $this->perfectFriendForm->setStatus(auth()->id() );

            User::find(auth()->id())->searchable(); //after registration we will index the user and user_profile

            return $this->successResponse($published );

        }
        catch(\Exception $e) {

            \Log::error($e);
            return $this->errorResponse('Unable to update user status', Response::HTTP_UNPROCESSABLE_ENTITY);

        }

    }

    /* Tag as skip or continue */
    public function skipOrContinue(){
        $this->profile
            ->where('user_id',authUser()->id)
            ->update(['is_step4_skipped' => 1]);
    }
}
