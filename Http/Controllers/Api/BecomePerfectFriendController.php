<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BecomePerfectFriendRequest;
use App\Models\BecomePerfectFriend;
use App\Models\Media;

use App\Models\User;
use App\Models\UserInterestingOn;
use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BecomePerfectFriendController extends Controller
{

    public function index(Request $request)
    {
        $user = User::with([
                    'photos',
                    'profile',
                    'interest',
                    'booking',
                    'rate'
                ])
                ->where('id',Auth::user()->id)
                ->get();
        return response(['users'=>$user], 200);
    }

     /**
     * Handle a form for 
     * becoming a friend request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(BecomePerfectFriendRequest $request)
    {
        try {
            DB::beginTransaction();
            $userId = Auth::user()->id;
            //Store Basic Info DB::become_perfect_friends
            $checkBecomingPerfectFriend = BecomePerfectFriend::where('user_id', $userId)
                                            ->first();
            $form = [
                'about_yourself' => $request -> about_yourself,
                'occupation' => ucwords($request -> occupation),
                'relationship_status' => ucwords($request -> relationship_status),
                'education' => $request -> education,
                'primary_language' => $request -> primary_language,
                'are_you_smoker' => (isset($request -> are_you_smoker) ? 1 : 0),
                'are_you_drinker' => (isset($request -> are_you_drinker) ? 1 : 0),
                'are_you_in_relationship' => (isset($request -> are_you_in_relationship) ? 1 : 0),
                'do_you_have_children' => (isset($request -> do_you_have_children) ? 1 : 0),
                'user_id' => $userId,
            ];

            if (!$checkBecomingPerfectFriend) {
                BecomePerfectFriend::create($form);
            }
            else {
                BecomePerfectFriend::where('user_id',$userId) 
                        ->update($form);
            }

            // Select current user
            $getUser = User::whereId($userId)
                                ->first();

            if ($request->image) {
                Media::unlinkMedia($request->image);
                $filPath = Media::addMedia($request->image);
                if (!$filPath) {
                    return false;
                }
                else {
                    //Else update existing image by the new image url
                    $getUser->image = $filPath;
                    $getUser->save();
                }
            }

            //Save Other Photos DB::additional_photos
            //Loop all the additional image and save into s3

            if ($request->additional_images) {
                UserPhoto::where('user_id', $userId)
                                    ->delete();

                foreach($request->additional_images as $otherImages) {
                    $filPath = Media::addMedia($otherImages);
                    if (!$filPath) {
                        return false;
                    }
                    // Else store the url of image that save from s3
                    else {
                        //delete all additional files if exist
                        $additional_photos = UserPhoto::where('user_id', $userId)
                                                ->first();
                        if ($additional_photos) {
                            Media::unlinkMedia($additional_photos->image);
                        }

                        UserPhoto::create([
                            'user_id' => $userId,
                            'media_id' => $filPath
                        ]);
                    }
                }
            }

            // Save Interested In DB::user_interesting_ons
            if ($request->interested_on) {
                $interested_array = array();
                $interested_on = UserInterestingOn::where('user_id',$userId)
                                    ->first();
                foreach($request->interested_on as $interested) {
                    $interested_array[] = $interested; 
                }
                if ($interested_on) {
                    $interested_on->interested = json_encode($interested_array);
                }
                else {
                    UserInterestingOn::create([
                        'user_id' => $userId,
                        'interested' => json_encode($interested_array)
                    ]);
                }
            }
            DB::commit();
            return response(['success' => true], 200);
        } 
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
       
    }

    public function setRatePerHour(Request $request) 
    {
        try {
            $validator = Validator::make($request->all(), [
                'rate_per_hour' => 'required'
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }

            DB::beginTransaction();
            $userId = Auth::user()->id;

            $memberRate = BecomePerfectFriend::where('user_id', $userId)
                        ->first();
            BecomePerfectFriend::where('user_id',$userId)
                        ->update([
                            'rate_per_hour' => $request->rate_per_hour
                        ]);
            DB::commit();
            return response(['success' => true], 200);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
