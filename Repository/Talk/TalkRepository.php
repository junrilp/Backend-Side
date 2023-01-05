<?php

namespace App\Repository\Talk;

use App\Enums\TalkActions;
use App\Http\Resources\TalkResource;
use App\Http\Resources\UserTalkResource;
use App\Models\Talk;
use App\Models\User;
use App\Models\UserTalk;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TalkRepository implements TalkInterface
{
    use ApiResponser;

    /**
     * @param int $pageSize
     *
     * @return [type]
     */
    public function all(int $pageSize = 20)
    {
        return Talk::paginate($pageSize);
    }

    public function get()
    {
        $talks = Talk::select('id', 'stream_id')
            ->whereNotNull('stream_id')
            ->get();

        $validTalkIds = [];
        $invalidTalkIds = [];

        foreach ($talks as $talk) {
            if ($this->validateBroadcast($talk->stream_id)) {
                $validTalkIds[] = $talk->id;
            } else {
                $invalidTalkIds[] = $talk->id;
            }
        }

        return Talk::whereIn('id', $validTalkIds)
            ->paginate();
    }

    public function validateBroadcast(string $streamId)
    {
        try {
            //connect to the Media Server
            $stream = Http::get(env('ANTMEDIA_URL') . '/rest/v2/broadcasts/' . $streamId)
                ->json();

            if ($stream) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param int $ownerId
     * @param int $pageSize
     *
     * @return [type]
     */
    public  function getByOwnerId(int $ownerId, int $pageSize = 20)
    {
        return TalkResource::collection(Talk::whereOwnerId($ownerId)->paginate($pageSize));
        //return Talk::whereOwnerId($ownerId)->paginate($pageSize);
    }

    /**
     * @param array $talkDetails
     *
     * @return [type]
     */
    public function create(array $talkDetails)
    {
        $talk = Talk::create($talkDetails);

        //connect to the Media Server
        $result = Http::post(env('ANTMEDIA_URL') . '/rest/v2/broadcasts/conference-rooms', [
            'roomId' => $talk->id
        ])->throw(function ($response, $e) {
            Log::debug("TalkRepository");
            Log::debug($response);
        })->json();

        //then add this user to the room
        $this->allowAttendeeToUnmute(
            $talk,
            $talk->owner_id,
            $talk->owner_id,
            TalkActions::ALLOW_UNMUTE,
            TalkActions::TOGGLE_MUTE
        );

        return $talk;
    }

    /**
     * @param int $talkId
     * @param int $ownerId
     * @param array $talkDetails
     *
     * @return [type]
     */
    public  function update(int $talkId, int $ownerId, array $talkDetails)
    {
        $talk = Talk::find($talkId);
        if (!$talk) {
            throw new Exception("There's no Talk session with that ID", Response::HTTP_NOT_FOUND);
        }

        if ($talk->owner_id != $ownerId) {
            throw new Exception("You are not allowed to edit this Talk", Response::HTTP_FORBIDDEN);
        }

        //only allow editing of the talk's clip, if the talk is premium/paid
        if (array_key_exists('session_url', $talkDetails) && $talk->is_paid == 0) {
            throw new Exception("You are not allowed to update the clip for a non-premium Talk", Response::HTTP_FORBIDDEN);
        }

        $talk->update($talkDetails);

        return $talk;
    }

    /**
     * @param int $talkId
     * @param int $ownerId
     *
     * @return [type]
     */
    public  function delete(int $talkId, int $ownerId)
    {
        $talk = Talk::find($talkId);
        if (!$talk) {
            throw new Exception("There's no Talk session with that ID", Response::HTTP_NOT_FOUND);
        }

        if ($talk->owner_id != $ownerId) {
            //    throw new Exception("You are not allowed to delete this Talk", Response::HTTP_FORBIDDEN);
        }

        $talk->delete();

        //we can put the delete function at the end
        $result = Http::delete(env('ANTMEDIA_URL') . '/rest/v2/broadcasts/' . $talkId)
            ->throw(function ($response, $e) {
                Log::debug("TalkRepository delete error");
                Log::debug($response);
            })->json();

        Log::debug("TalkRepository delete");
        Log::debug($result);

        return $talk;
    }


    /**
     * @param int $talkId
     * @param int $userId
     *
     * @return [type]
     */
    public function join(int $talkId, int $userId)
    {
        $talk = $this->getById($talkId);

        //todo: refactor later for binding with the User model
        try {
            $userTalk = UserTalk::updateOrCreate([
                'user_id' => $userId,
                'talk_id' => $talkId,
                'status' => UserTalk::STATUS_USER_REQUESTED,
            ]);

            $response = [
                'status' => 'ok',
                'data' => $userTalk,
            ];
        } catch (Exception $e){
            $response = [
                'status' => 'error',
                'data' => $e->getMessage(),
            ];
        }

        return $response;
    }

    public function getById(int $talkId)
    {
        $talk = Talk::find($talkId);
        if (!$talk) {
            throw new Exception("There's no Talk session with that ID", Response::HTTP_NOT_FOUND);
        }

        return $talk;
    }

    public function removeAttendee(int $talkId, int $ownerId, int $userId)
    {
        $talk = $this->getById($talkId);

        //todo: refactor later for binding with the User model
        $userTalk = UserTalk::where([
            'user_id' => $userId,
            'talk_id' => $talk->id
        ])->get()->first();

        if (!$userTalk) {
            throw new Exception("This user has not yet invited", Response::HTTP_NOT_FOUND);
        }

        if ($ownerId != $talk->owner_id) {

            // Check if owner is still in session
            $ownerActive = UserTalk::where('user_id', $talk->owner_id)
                ->first();

            if ($ownerActive) {
                throw new Exception("You are not allowed to remove this user", Response::HTTP_FORBIDDEN);
            }
        }

        $userTalk->delete();

        return $userTalk;
    }

    public function addAttendees(int $talkId, int $ownerId, $userIds)
    {
        $talk = $this->getById($talkId);

        if ($ownerId != $talk->owner_id) {
            throw new Exception("You are not allowed to add attendees", Response::HTTP_FORBIDDEN);
        }

        $data = [];
        foreach ($userIds as $userId) {
            $data[] = UserTalk::create([
                'user_id' => $userId,
                'talk_id' => $talk->id,
                'status' => UserTalk::STATUS_INVITED
            ]);
        }

        return $data;
    }


    public function stop(int $talkId, int $ownerId)
    {
        $talk = $this->getById($talkId);

        if ($ownerId != $talk->owner_id) {
            throw new Exception("You are not allowed to stop this session", Response::HTTP_FORBIDDEN);
        }

        //connect to AntMedia
        $result = Http::delete(env('ANTMEDIA_URL') . '/rest/v2/broadcasts/conference-rooms/' . $talk->id)
            ->throw(function ($response, $e) {
                Log::debug("TalkRepository delete room");
                Log::debug($response);
            })->json();

        Log::debug("TalkRepository delete room result");
        Log::debug($result);

        return $talk;
    }


    public function allowAttendeeToUnmute(Talk $talk, int $ownerId, int $attendeeId, bool $allow, bool $toggle)
    {
        $userTalk = UserTalk::where([
            'user_id' => $attendeeId,
            'talk_id' => $talk->id
        ])->first();

        if (!$userTalk) {
            //check if the the user to be added is the owner
            if ($ownerId == $attendeeId) {
                $userTalk = UserTalk::updateOrCreate([
                    'user_id' => $ownerId,
                    'talk_id' => $talk->id,
                    'status' => UserTalk::STATUS_ACTIVE,
                ]);
            } else {
                throw new Exception("This user has not yet invited", Response::HTTP_NOT_FOUND);
            }
        }

        if ($ownerId != $talk->owner_id) {
            throw new Exception("You are not allowed to mute/unmute this user", Response::HTTP_FORBIDDEN);
        }

        if ($allow) {
            $result = Http::post(env('ANTMEDIA_URL') . '/rest/v2/broadcasts/create', [
                'name' => "host-$userTalk->id",
                'publish' => true,
                'description' => User::find($attendeeId)->user_name,
            ])->throw(function ($response, $e) {
                Log::debug("TalkRepository allowAttendeeToUnmute");
                Log::debug($response);
            })->json();

            $talk->stream_id = $result['streamId'];
            $talk->save();

            $userTalk->stream_id = $result['streamId'];

            //then add to the room
            $result = Http::put(env('ANTMEDIA_URL') . "/rest/v2/broadcasts/conference-rooms/$talk->id/add?streamId=$userTalk->stream_id")
                ->throw(function ($response, $e) {
                    Log::debug("TalkRepository allowAttendeeToUnmute:add to room");
                    Log::debug($response);
                })->json();
        }

        if ($toggle) {
            if ($userTalk->status == UserTalk::STATUS_MUTED) {
                $userTalk->status = UserTalk::STATUS_UNMUTED;
            } else {
                $userTalk->status = UserTalk::STATUS_MUTED;
            }
        }

        $userTalk->can_unmute = $allow;
        $userTalk->save();

        return $userTalk;
    }

    public function sendMessage(int $talkId, int $attendeeId, string $message)
    {
        $talk = $this->getById($talkId);
        $userTalk = UserTalk::where([
            'user_id' => $attendeeId,
            'talk_id' => $talk->id
        ])->get()->first();

        if (!$userTalk) {
            throw new Exception("This user has not yet invited", Response::HTTP_NOT_FOUND);
        } else {
            $userTalk->status = json_decode($message)->action;
            $userTalk->save();
        }

        //connect to AntMedia
        $result = Http::post(env('ANTMEDIA_URL') . '/rest/v2/broadcasts/' . $talk->stream_id . '/data', [$message])
            ->throw(function ($response, $e) {
                Log::debug("TalkRepository sendMessage");
                Log::debug($response);
            })->json();

        return $message;
    }

    public function getAttendees(int $talkId, int $ownerId, int $pageSize = 20)
    {
        $talk = $this->getById($talkId);

        //usage of $ownerId can be provided later once we have business rules on additional info that can be provided
        //to the owner
        return UserTalkResource::collection(UserTalk::whereTalkId($talk->id)->paginate($pageSize));
    }
}
