<?php

namespace App\Repository\Friend;

use App\Enums\FriendStatus;
use App\Models\Friend;
use Illuminate\Support\Facades\DB;


class FriendRepository implements FriendInterface
{

    /**
     * Check if user1 and user2 has friendship exists
     * @param int $userId
     * @param int $userId2
     * @param int|null $status
     *
     * @var mixed $friend
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function findFriend( int $userId, int $userId2, int $status = null ) {

        $friend = Friend::where(function ($query) use ($userId, $userId2) {
            $query->where('user_id_1', $userId)
                ->orWhere('user_id_2', $userId);
        })
        ->where(function ($query) use ($userId2) {
            $query->where('user_id_1', $userId2)
                ->orWhere('user_id_2', $userId2);
        });

        if ($status) {
            $friend = $friend->where('status', $status);
        }

        return $friend->first();

    }

    /**
     * Friend Request
     * @param int $userId
     * @param int|null $userId2
     *
     * @var mixed $friend
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function requestFriend(int $userId, int $userId2 = null)
    {
        $friend = Friend::where(function ($query) use ($userId, $userId2) {
            $query->where('user_id_1', $userId)
                ->orWhere('user_id_2', $userId);
        })
            ->where(function ($query) use ($userId2) {
                $query->where('user_id_1', $userId2)
                    ->orWhere('user_id_2', $userId2);
            })
            ->first();

        if ($friend) {
            return $friend;
        }

        $friend = Friend::create([
            'user_id_1' => $userId,
            'user_id_2' => $userId2,
            'status'    => FriendStatus::PENDING,
        ]);

        return $friend;

    }

    /**
     * @param int $userId
     * @param string|null $status
     * @param int $perPage=12
     *
     * @return [type]
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function getFriends(int $userId, string $status = null, int $perPage=12, $idOnly = false, $hasLimit = false)
    {

        if ($status==null) {

            $friends = Friend::with(['user1', 'user2', 'user1.primaryPhoto', 'user2.primaryPhoto', 'user1.interests', 'user2.interests', ])
             ->select(DB::raw("*, IF(user_id_1 = $userId,1,0) as initiated"));

            $friends = $friends->where(function ($query) use ($userId) {
                $query->where('user_id_1', $userId)
                    ->orWhere('user_id_2', $userId);
            });

            $friends = $friends->where('status', FriendStatus::ACCEPTED);

        }

        if ($status=='sent') {

            $friends = Friend::with(['user1', 'user2', 'user1.primaryPhoto', 'user2.primaryPhoto', 'user1.interests', 'user2.interests', ])
                ->select(DB::raw("*, IF(user_id_1 = $userId,1,0) as initiated"))
                ->where('user_id_1', $userId)
                ->where('status', FriendStatus::PENDING);

        }

        if ($status=='requested') {
            $friends = Friend::with(['user1', 'user2', 'user1.primaryPhoto', 'user2.primaryPhoto', 'user1.interests', 'user2.interests', ])
                    ->where('user_id_2', $userId)
                    ->where('status', FriendStatus::PENDING);

        }

        if ($hasLimit) {
            return $friends->limit($perPage)
                    ->orderBy('id', 'ASC')
                    ->simplePaginate($perPage); //default browse page
        }

        if ($idOnly) {

            $userIds = array();

            $friends = $friends->orderBy('updated_at', 'DESC')->get();

            foreach ($friends as $friend) {
                if ($friend->initiated===1) {
                    array_push($userIds, $friend->user_id_2);
                } else {
                    array_push($userIds, $friend->user_id_1);
                }
            }

            return $userIds;
        }

        $friends = $friends->orderBy('updated_at', 'DESC')->simplePaginate($perPage);

        return $friends;

    }


    public static function getFriendsCount(int $userId)
    {

        $friends = Friend::with(['user1', 'user2', 'user1.primaryPhoto', 'user2.primaryPhoto', 'user1.interests', 'user2.interests',])
            ->select(DB::raw("*, IF(user_id_1 = $userId,1,0) as initiated"));

            $friends = $friends->where(function ($query) use ($userId) {
                $query->where('user_id_1', $userId)
                    ->orWhere('user_id_2', $userId);
            });

          return $friends->where('status', FriendStatus::ACCEPTED)->count();


    }


}
