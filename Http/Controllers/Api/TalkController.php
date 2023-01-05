<?php

namespace App\Http\Controllers\Api;

use App\Events\TalkActionsEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\TalkResource;
use App\Http\Resources\UserTalkResource;
use App\Models\Talk;
use App\Models\UserTalk;
use App\Repository\Talk\TalkRepository;
use App\Traits\ApiResponser;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TalkController extends Controller
{
    use ApiResponser;

    protected $repository;

    public function __construct(TalkRepository $talkRepository)
    {
        $this->repository = $talkRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $pageSize = request()->get('perPage', 10);

        //TODO: apply business rules on getting the sessions (private, paid etc)
        if (request()->has('userId')) {
            $userId = request()->get('userId');
            return $this->repository->getByOwnerId($userId, $pageSize);
        }

        $talks = $this->repository->get();

        return $this->successResponse(TalkResource::collection($talks), 'Get talks', Response::HTTP_OK);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = request()->only([
            'owner_id',
            'description',
            'title',
            'is_paid',
            'media_id',
            'is_paid',
            'amount',
            'is_private',
            'start',
            'duration',
            'linked_entity',
            'linked_entity_id'
        ]);
        $data['owner_id'] = authUser()->id;
        $talk = $this->repository->create($data);

        return $this->successResponse(new TalkResource($talk), 'Created', Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param Talk $talk
     * @return ResponseFactory|JsonResponse|\Illuminate\Http\Response
     */
    public function show(Talk $talk)
    {
        return $this->successResponse(new TalkResource($talk), 'Get single talk.', Response::HTTP_OK);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //can only be updated by the owner
        $data = request()->only([
            'description',
            'title',
            'is_paid',
            'media_id',
            'is_paid',
            'amount',
            'is_private',
            'start',
            'duration',
            'linked_entity',
            'linked_entity_id',
            'session_url'
        ]);
        $ownerId = authUser()->id;
        $talk = $this->repository->update($id, $ownerId, $data);

        return $this->successResponse(new TalkResource($talk), 'Updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $ownerId = authUser()->id;
        $talk = $this->repository->delete($id, $ownerId);

        return $this->successResponse(new TalkResource($talk), 'Deleted');
    }

    public function join(Talk $talk)
    {
        $userId = authUser()->id;
        $talkUser = $this->repository->join($talk->id, $userId);

        if ($talkUser['status'] == 'error') {
            return $this->errorResponse($talkUser['data'], Response::HTTP_BAD_REQUEST);
        }

        // TODO: update to not check events in tests
        broadcast(new TalkActionsEvent(authUser(), $talk, $talkUser['data'], 'join_request'));

        return $this->successResponse(new UserTalkResource($talkUser['data']), 'Joined', Response::HTTP_CREATED);
    }

    public function removeAttendee($id, $userId)
    {
        $ownerId = authUser()->id;
        $talkUser = $this->repository->removeAttendee($id, $ownerId, $userId);

        $talk = Talk::find($id);

        broadcast(new TalkActionsEvent(authUser(), $talk, $talkUser, 'leave_request'));

        return $this->successResponse($talkUser, 'Removed');
    }

    public function addAttendees($id)
    {
        $ownerId = authUser()->id;
        $userIds = request()->get('user_ids');

        $talkUsers = $this->repository->addAttendees($id, $ownerId, $userIds);

        return $this->successResponse($talkUsers, 'Added', Response::HTTP_CREATED);
    }

    public function stop($id)
    {
        $ownerId = authUser()->id;
        Log::debug('ownerId ' . $ownerId . ' talkId: ' . $id);
        $data = $this->repository->stop($id, $ownerId);

        $userTalk = UserTalk::where([
            'user_id' => $ownerId,
            'talk_id' => $id
        ])->first();

        broadcast(new TalkActionsEvent(authUser(), $data, $userTalk, 'stop_talk'));

        return $this->successResponse($data, 'Stopped');
    }

    public function allowAttendeeToUnmute(Talk $talk, $userId)
    {
        $ownerId = authUser()->id;
        $toggle = false;

        $params = request('allowToUnmute');

        if (isset($params)) {
            $allowToUnmute = request('allowToUnmute', false);
        } else {
            $allowToUnmute = 'toggle';
            $toggle = true;
        }

        $talkUser = $this->repository->allowAttendeeToUnmute($talk, $ownerId, $userId, $allowToUnmute, $toggle);

        if (request('allowToUnmute')) {
            broadcast(new TalkActionsEvent(authUser(), $talk, $talkUser, 'allow_unmute'));
        } else {
            broadcast(new TalkActionsEvent(authUser(), $talk, $talkUser, 'toggle_mute'));
        }

        return $this->successResponse($talkUser, 'Updated');
    }

    public function sendAction($id)
    {
        $userId = authUser()->id;
        $talk = Talk::find($id);
        $userTalk = UserTalk::where([
            'user_id' => $userId,
            'talk_id' => $talk->id
        ])->get()->first();
        $action = request('action', 'hand_raised');
        $data = $this->repository->sendMessage($id, $userId, json_encode(['action' => $action, 'user_id' => $userId]));

        broadcast(new TalkActionsEvent(authUser(), $talk, $userTalk, $action));

        return $this->successResponse($data, 'Sent');
    }

    public function getAttendees($id)
    {
        $ownerId = authUser()->id;
        $pageSize = request()->get('perPage', 10);

        $talkUsers = $this->repository->getAttendees($id, $ownerId, $pageSize);
        return $this->successResponse($talkUsers);
    }
}
