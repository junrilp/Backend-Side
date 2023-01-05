<?php

namespace App\Http\Controllers\Api;

use App\Enums\FriendStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\FriendResource;
use App\Models\Notification;
use App\Models\User;
use App\Repository\Friend\FriendRepository;
use App\Traits\ApiResponser;
use App\Traits\Friendable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class FriendController extends Controller
{
    use ApiResponser, Friendable;

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function requestFriend(Request $request)
    {

        // Guard Clause 1 - Make sure the user trying to friend exists in the system.

        if (!$this->userExists($request->user_id)) {
            return $this->errorResponse('The friend you are trying to add does not exist.', Response::HTTP_BAD_REQUEST);
        }

        $friend = User::find($request->user_id);


        // Guard Clause 2 - Check if there is already a friend request setup for this person.
        $friendExist = FriendRepository::findFriend($request->user()->id, $friend->id);

        if ($friendExist) {
            $message = $this->friendshipExistsMessage($friendExist);

            return $this->errorResponse($message, Response::HTTP_BAD_REQUEST);
        }
        $friend = User::with(['primaryPhoto', 'interests'])->find($request->user_id);

        // Request to be friends with the person.
        $friends = FriendRepository::requestFriend($request->user()->id, $friend->id, null, $request->message);

        return $this->successResponse($friends);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function respondFriend(Request $request)
    {
        $friend = FriendRepository::findFriend($request->user_id, authUser()->id);

        if (!$friend) {
            return $this->errorResponse('This pending friend request cannot be found.', Response::HTTP_BAD_REQUEST);
        }

        if ($friend->status!=FriendStatus::PENDING) {
            return $this->errorResponse('You don\'t have a pending request', Response::HTTP_BAD_REQUEST);
        }


        if (($friend->user_id_1 != $request->user()->id) && $friend->user_id_2 != $request->user()->id) {
            return $this->errorResponse('You do not have permission to modify this friendship.', Response::HTTP_BAD_REQUEST);
        }

        if (($friend->user_id_1 == $request->user()->id) && $request->status == 'accept') {
            return $this->errorResponse('You cannot accept your own friend request.', Response::HTTP_BAD_REQUEST);
        }

        if ($request->status=='accept') {
            $friend->update(['status'   => FriendStatus::ACCEPTED]);
        }

        if ($request->status=='reject') {
            $friend->update(['status'   => FriendStatus::REJECTED]);
        }

        return $this->successResponse($friend);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function getFriends(Request $request)
    {

        if ($request->username != '') {
            $userId = User::where('user_name', $request->username)->firstOrFail()->id;
        } else {
            $userId  = authUser()->id;
        }

        $status = isset($request->status) ? $request->status : null;

        $friends = FriendRepository::getFriends($userId, $status, $request->limit ?? 12, $request->hasLimit ?? false);

        return $this->successResponse(FriendResource::collection($friends), '', Response::HTTP_OK, true);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function destroy(Request $request)
    {
        $friend = FriendRepository::findFriend($request->friend, authUser()->id);

        if (!$friend) {
            return $this->errorResponse('This friendship cannot be found.', Response::HTTP_BAD_REQUEST);
        }

        if (($friend->user_id_1 != $request->user()->id) && $friend->user_id_2 != $request->user()->id) {
            return $this->errorResponse('You do not have permission to modify this friendship.', Response::HTTP_BAD_REQUEST);
        }

        if ($friend->delete()) {
            // delete sent friend request notification
            Notification::where('data->type', 'friend_request')
                ->where('notifiable_id', $request->friend)
                ->where('data->user_id', authUser()->id)
                ->where('read_at', null)
                ->delete();
        }

        return $this->successResponse(null, '', Response::HTTP_OK);
    }
}
