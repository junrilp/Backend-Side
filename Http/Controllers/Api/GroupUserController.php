<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GroupInviteeAuthorizedRequest;
use App\Http\Resources\GroupInvitationResource;
use App\Http\Resources\GroupNotificationResource;
use App\Http\Resources\GroupResource;
use App\Models\GroupMemberInvite;
use App\Notifications\GroupInviteAcceptedNotification;
use App\Notifications\GroupInviteRejectedNotification;
use App\Repository\Group\GroupRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GroupUserController extends Controller
{
    /**
     * @use: $this->successResponse(message, status_code)
     * @use: $this->errorResponse(message, status_code)
     */
    use ApiResponser;

    //test

    private $groupRepository;

    public function __construct(GroupRepository $groupRepository)
    {
        $this->groupRepository = $groupRepository;
    }

    /**
     * @param GroupMemberInvite $groupInvite
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function acceptGroupInvite(GroupInviteeAuthorizedRequest $request, GroupMemberInvite $groupInvite)
    {
        try {
            $groupInvite = $this->groupRepository->userAcceptGroupInvite($groupInvite->id);
            Notification::send(
                $groupInvite->group->user,
                new GroupInviteAcceptedNotification($groupInvite->group, $groupInvite->user)
            );
            return $this->successResponse(
                (new GroupResource($groupInvite->group))->setAuthUserId(authUser()->id ?? 0),
                sprintf("You are now a member of %s", $groupInvite->group->name)
            );
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('We are unable to process group invite.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param GroupMemberInvite $groupInvite
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function rejectGroupInvite(GroupInviteeAuthorizedRequest $request, GroupMemberInvite $groupInvite)
    {
        try {
            $groupInvite = $this->groupRepository->userRejectGroupInvite($groupInvite->id, true);
            Notification::send(
                $groupInvite->group->user,
                new GroupInviteRejectedNotification($groupInvite->group, $groupInvite->user)
            );
            return $this->successResponse(
                (new GroupResource($groupInvite->group))->setAuthUserId(authUser()->id ?? 0),
                sprintf(" %s group invite successfully declined. Thank you.", $groupInvite->group->name)
            );
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('We are unable to process group invite.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function userGroupPendingInvites(Request $request)
    {
        $groupInvites = $this->groupRepository->getUserGroupPendingInvites(authUser()->id, $request->only('perPage'));
        return $this->successResponse(
            GroupInvitationResource::collection($groupInvites),
            'Success',
            Response::HTTP_OK,
            true
        );
    }

    /**
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function userGroupStatistics()
    {
        $statistics = $this->groupRepository->getUserGroupStatistics(auth()->id());
        return $this->successResponse($statistics);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function groupUserNotifications(Request $request)
    {
        $notifications = $this->groupRepository->getUserUnreadNotifications($request->user()->id);
        return $this->successResponse(
            GroupNotificationResource::collection($notifications),
            'Success',
            Response::HTTP_OK,
            true
        );
    }
}
