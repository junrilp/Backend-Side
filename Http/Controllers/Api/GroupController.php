<?php

namespace App\Http\Controllers\Api;

use Exception;
use Throwable;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Group;
use App\Traits\AdminTraits;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Models\GroupMemberInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Events\UserDiscussionEvent;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\TypeResource;
use App\Http\Resources\GroupResource;
use App\Http\Requests\GroupNameRequest;
use App\Http\Requests\GroupSaveRequest;
use App\Http\Requests\GroupMediaRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\GroupSearchRequest;
use App\Http\Resources\GroupNameResource;
use App\Repository\Group\GroupRepository;
use App\Http\Resources\PastInviteResource;
use App\Http\Resources\UserBasicInfoResource;
use App\Http\Resources\GroupMemberResource;
use App\Http\Resources\UserSearchResource;
use App\Http\Requests\GroupCategoryRequest;
use App\Repository\Search\SearchRepository;
use Illuminate\Support\Facades\Notification;
use App\Http\Requests\GroupAuthorizedRequest;
use App\Http\Requests\GroupDescriptionRequest;
use App\Http\Resources\GroupResourceCollection;
use App\Http\Resources\GroupMemberInviteResource;
use App\Notifications\GroupNewMemberNotification;
use App\Repository\Invitation\InvitationRepository;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Notifications\GroupInvitationReceivedNotification;

class GroupController extends Controller
{
    use ApiResponser, ValidatesRequests, AdminTraits;

    private $groupRepository;
    private $searchRepository;
    private $invitationRepository;

    public function __construct(GroupRepository $groupRepository, SearchRepository $searchRepository, InvitationRepository $invitationRepository)
    {
        $this->groupRepository = $groupRepository;
        $this->searchRepository = $searchRepository;
        $this->invitationRepository = $invitationRepository;
    }

    /**
     * Search, filter, and sort groups.
     * @param GroupSearchRequest $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function index(Request $request)
    {
        $parts = parse_url($request->header('referer'));
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (Arr::has($query, 'created_at')){
                $request->request->add(['created_at' => $query['created_at']]);
            }
        }

        $day = NULL;

        if (authCheck()) {
            $day =  Carbon::createFromFormat('Y-m-d', authUser()->birth_date)->format('m-d');
        }

        try {
            $keywords = strtolower($request->get('keyword'));
            $categoryId = Arr::get($request,'category_id', 0);
            $typeId = Arr::get($request,'type_id', 0);
            $options = array_merge($request->only([
                'type_id',
                'category_id',
                'perPage',
                'page',
                'sort',
                'sortBy',
                'include_unpublished',
                'created_at'
            ]), [
                'relations' => [
                    'media',
                    'members',
                    'user'
                ],
                'exclude_by_owner' => filter_var($request->get('include_owned'), FILTER_VALIDATE_BOOLEAN) ? null : authUser()->id,
                'custom_query' => function ($query) use ($day, $keywords, $categoryId, $typeId) {
                    if (!$keywords && !$categoryId && !$typeId) $query->isNotPf()->OrWhere('birthday', $day);
                }
            ]);
            $results = $this->groupRepository->search($keywords, $options);

            return $this->successResponse((new GroupResourceCollection($results))->setAuthUserId(authUser()->id ?? 0), 'Success', Response::HTTP_OK, true);
        } catch (Exception $e) {

            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param GroupNameRequest $request
     *
     * @return JsonResponse
     */
    public function store(GroupSaveRequest $request)
    {
        try {

            $userId = authUser()->id ?? 0;
            $group = DB::transaction(function () use ($request, $userId) {
                return $this->groupRepository->create($request->all(), $userId);
            });
            $group->load([
                'media',
                'video',
                'type',
                'category',
                'tags',
            ]);

            return $this->successResponse((new GroupResource($group))->setAuthUserId($userId), 'Group successfully created.', Response::HTTP_CREATED);
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to process your request. ', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param GroupNameRequest $request
     *
     * @return JsonResponse
     */
    public function groupName(GroupNameRequest $request): JsonResponse
    {
        $userId = authUser()->id;
        $group = $this->groupRepository->create([
            'name' => $request->name,
            'live_chat_enabled' => $request->live_chat_enabled
        ], $userId);
        return $this->successResponse(new GroupNameResource($group), Response::HTTP_CREATED);
    }

    /**
     * Update group details
     *
     * @param GroupSaveRequest $request
     * @param Group $group
     * @return mixed $result
     */
    public function update(GroupSaveRequest $request, Group $group)
    {
        try {

            $userId = authUser()->id ?? 0;
            $group = DB::transaction(function () use ($request, $group, $userId) {
                $attributes = $request->only(array_merge($group->getFillable(), ['tags']));
                if ($image = $request->file('image')) {
                    $attributes['image'] = $image;
                } elseif ($image = $request->post('image')) {
                    $attributes['image'] = $image;
                }

                return $this->groupRepository->update($group, $attributes, $userId);
            });
            $group->load([
                'media',
                'video',
                'type',
                'category',
                'tags',
            ]);

            return $this->successResponse((new GroupResource($group))->setAuthUserId($userId), 'Group successfully updated.', Response::HTTP_ACCEPTED);
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to update group.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get group details
     *
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function show(Group $group)
    {
        $userId = authUser()->id ?? 0;
        $group = Group::query()
            ->withAll()
            ->findOrFail($group->id);
        return $this->successResponse((new GroupResource($group))->setAuthUserId($userId), 'Group successfully show.', Response::HTTP_ACCEPTED);
    }

    /**
     * Delete group
     *
     * @param GroupAuthorizedRequest $request
     * @param Group $group
     *
     * @return mixed $result
     */
    public function destroy(GroupAuthorizedRequest $request, Group $group)
    {
        try {
            $userId = authUser()->id ?? 0;
            $group = $this->groupRepository->delete($group);
            return $this->successResponse((new GroupResource($group))->setAuthUserId($userId), 'Successfully Deleted!');
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            return $this->errorResponse('Unable to delete group.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get/search user groups
     *
     * @param Request $request
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Illuminate\Validation\ValidationException
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function userGroups(Request $request)
    {
        $this->validate($request, [
            'type' => 'in:my-groups,joined,all'
        ]);

        $keywords = $request->get('keyword');
        $options = $request->all();
        $options['relations'] = [
            'user',
            'media',
            'members'
        ];
        $results = $this->groupRepository->searchUserGroups(authUser()->id, $keywords, $options);
        return $this->successResponse((new GroupResourceCollection($results))->setAuthUserId(authUser()->id ?? 0), 'Success', Response::HTTP_OK, true);
    }

    /**
     * Get type and category
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Illuminate\Validation\ValidationException
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function groupFilterAndOptions(Request $request)
    {
        $availableOptions = [
            'all',
            'types',
            'categories',
        ];
        $this->validate($request, [
            'only' => Rule::in($availableOptions),
        ]);

        $selectedOptions = $request->get('only')
            ? (is_array($request->get('only'))
                ? $request->get('only')
                : array_map('trim', explode(',', $request->get('only')))
            )
            : $availableOptions;

        $options = [];
        if (in_array('types', $selectedOptions) || in_array('all', $selectedOptions)) {
            $options['types'] = TypeResource::collection($this->groupRepository->getTypeOptions());
        }
        if (in_array('categories', $selectedOptions) || in_array('all', $selectedOptions)) {
            $options['categories'] = CategoryResource::collection($this->groupRepository->getCategoryOptions());
        }
        return $this->successResponse($options);
    }

    /**
     * Get related groups
     *
     * @param Request $request
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function relatedGroups(Request $request, Group $group)
    {
        $results = $this->groupRepository->getRelatedGroups($group, $request->get('perPage', 6));
        return $this->successResponse(
            (new GroupResourceCollection($results))->setAuthUserId(authUser()->id ?? 0),
            sprintf("%s related groups found.", $results->total()),
            Response::HTTP_OK,
            true
        );
    }

    /**
     * Published or unpublished group
     *
     * @param GroupAuthorizedRequest $request
     * @param Group $group
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function publishGroup(GroupAuthorizedRequest $request, Group $group)
    {
        try {
            $userId = authUser()->id ?? 0;
            $group = $this->groupRepository->changePublishStatusGroup($group, $request->post('status', 'toggle'));

            if (isset($request->postToWall) && (int)$request->postToWall === 1) {
                event(new UserDiscussionEvent(authUser()->id, 'group_created', $group));
            }

            return $this->successResponse(
                (new GroupResource($group))->setAuthUserId($userId),
                sprintf('Group has been %s.', $group->isPublished() ? 'published' : 'unpublished')
            );
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to update group.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get group member list
     *
     * @param Request $request
     * @param Group $group
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function groupMembers(Request $request, Group $group)
    {
        try {
            $keywords = $request->get('keyword');
            $options = $request->except('keyword');
            $members = $this->groupRepository->searchGroupMembers($group, $keywords, $options);
            return $this->successResponse(
                GroupMemberResource::collection($members),
                sprintf("%d group %s found.", $members->total(), Str::plural('members', $members->total())),
                Response::HTTP_OK,
                true
            );
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to update group.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get users available to invite, user with pending invite are excluded.
     * @param Request $request
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function getNonMembers(Request $request, Group $group)
    {
        $options = array_merge($request->all(), [
            'exclude_users' => [auth()->id()],
        ]);
        $users = $this->groupRepository->getNonMemberUsers($group, $request->get('keyword'), $options);
        return $this->successResponse(
            UserBasicInfoResource::collection($users),
            'Success',
            Response::HTTP_OK,
            $request->get('perPage', 1) > 0
        );
    }

    /**
     * Send an invitation to user(s)
     *
     * @param GroupAuthorizedRequest $request
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     * @throws \Illuminate\Validation\ValidationException
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function inviteMembers(GroupAuthorizedRequest $request, Group $group)
    {
        $request = $request->merge([
            'user_ids' => is_array($value = $request->post('user_ids')) ? $value : str_getcsv($value),
        ]);
        $this->validate($request, [
            'user_ids' => ['array', 'required'],
            'user_ids.*' => ['integer'],
        ]);

        try {
            $userIds = $request->input('user_ids');
            $groupInvites = $this->groupRepository->generateGroupMemberInvites($group, $userIds);
            $allInvites = $groupInvites->new->merge($groupInvites->existing)
                ->each(function (GroupMemberInvite $memberInvite) {
                    $memberInvite->load('user');
                });

            foreach ($groupInvites->new as $groupInvite) {
                if ($groupInvite->user->validTypeAccount) {
                    Notification::send($groupInvite->user, new GroupInvitationReceivedNotification($groupInvite));
                }
            }

            $responseData = [
                'summary' => [
                    'new_invites' => $groupInvites->new->count(),
                    'existing_invites' => $groupInvites->existing->count(),
                    'total_invites' => $allInvites->count(),
                    'already_members' => $groupInvites->members->count(),
                ],
                'users' => [
                    'new_invites' => $groupInvites->new->pluck('user_id'),
                    'existing_invites' => $groupInvites->existing->pluck('user_id'),
                    'all_invites' => GroupMemberInviteResource::collection($allInvites),
                    'already_members' => $groupInvites->members->pluck('id'),
                ],
            ];
            return $this->successResponse(
                $responseData,
                'Success'
            );
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to process your requests.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get invited users
     *
     * @param Request $request
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function memberInvites(Request $request, Group $group)
    {
        $options = array_merge($request->all(), [
            'relations' => ['user', 'group'],
        ]);
        $memberInvites = $this->groupRepository->getMemberInvites($group, $request->get('keyword'), $options);
        return $this->successResponse(
            GroupMemberInviteResource::collection($memberInvites),
            'Success',
            Response::HTTP_OK,
            true
        );
    }

    /**
     * Join current user to a group
     *
     * @param Request $request
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response|void
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function joinGroup(Request $request, Group $group)
    {
        try {
            $userId = authUser()->id ?? 0;
            $currentUser = $request->user();
            $result = $this->groupRepository->joinUserToGroup($currentUser->id, $group);
            $group->refresh()
                ->load([
                    'user',
                    'members',
                    'media'
                ]);

            if ($result) {
                Notification::send($group->user, new GroupNewMemberNotification($group, $currentUser));
            }

            if (isset($request->postToWall) && (int)$request->postToWall === 1) {
                event(new UserDiscussionEvent(authUser()->id, 'group_joined', $group->makeHidden(['user', 'members', 'media'])));
            }

            return $this->successResponse((new GroupResource($group))->setAuthUserId($userId), 'You are now a member of this group.');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to join to this group.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Leave current user to a group.
     *
     * @param Request $request
     * @param Group $group
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function leaveGroup(Request $request, Group $group)
    {
        try {
            $user = $request->user()->id ?? 0;
            $this->groupRepository->leaveUserFromGroup($user, $group);
            $group->refresh()
                ->load([
                    'user',
                    'media',
                    'members',
                ]);
            return $this->successResponse((new GroupResource($group))->setAuthUserId(authUser()->id ?? 0), 'Successfully leave the group.');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Unable to leave to this group.', Response::HTTP_BAD_REQUEST);
        }
    }

    // Admin

    public function delete(Group $group)
    {
        if ($this->isAdmin()) {
            try {
                $group->delete();
                return $this->successResponse((new GroupResource($group)));
            } catch (Throwable $exception) {
                Log::error($exception->getMessage());
                return $this->errorResponse('Unable to delete group.', Response::HTTP_BAD_REQUEST);
            }

        }

        return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
    }
    /**
     * @param GroupNameRequest $request
     * @param Group $group
     *
     * @return JsonResponse
     */
    public function updateName(GroupNameRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupRepository->update($group->id, [
            'name' => $request->name,
            'live_chat_enabled' => $request->live_chat_enabled
        ]);

        return $this->successResponse(new GroupNameResource($group), Response::HTTP_OK);
    }

    /**
     * @param GroupCategoryRequest $request
     * @param Group $group
     *
     * @return JsonResponse
     */
    public function updateCategory(GroupCategoryRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupRepository->update($group->id, [
            'interests' => $request->interests,
            'category_id' => $request->category,
            'type_id' => $request->type,
        ]);

        return $this->successResponse(new GroupNameResource($group), Response::HTTP_OK);
    }

    /**
     * @param GroupDescriptionRequest $request
     * @param Group $group
     *
     * @return JsonResponse
     */
    public function updateDescription(GroupDescriptionRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupRepository->update($group->id, [
            'description' => $request->description,
        ]);

        return $this->successResponse(new GroupNameResource($group), Response::HTTP_OK);
    }

    /**
     * @param GroupMediaRequest $request
     * @param Group $group
     *
     * @return JsonResponse
     */
    public function updateMedia(GroupMediaRequest $request, Group $group): JsonResponse
    {
        $group = $this->groupRepository->update($group->id, [
            'image' => $request->image_id,
            'video_id' => $request->video_id,
        ]);

        return $this->successResponse(new GroupNameResource($group), Response::HTTP_OK);
    }

    /**
     * @param GroupMediaRequest $request
     * @param Group $group
     *
     * @return JsonResponse
     */
    public function updateAdmins(Request $request, Group $group): JsonResponse
    {
        $userId = authUser()->id;

        $group = $this->groupRepository->updateAdmins($request->all(), $group, $userId);

        return $this->successResponse(new GroupNameResource($group), Response::HTTP_OK);
    }

    public function listInvitePastEvents(Request $request)
    {
        $perPage = 10;
        $keyword = $request->keyword;
        $user = User::find(authUser()->id);

        $result = $this->eventRepository->listInvitePastEvents($user, $keyword, $perPage);

        return $this->successResponse(PastInviteResource::collection($result), NULL, Response::HTTP_OK, true);
    }

    /**
     * Invite people from your past events
     *
     * @param Request $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function invitePastEvents(Request $request, Group $group)
    {

        try {
            $this->invitationRepository->invitePastEvents($request->all(), $group);

            return $this->successResponse(null, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function listInviteFriends(Request $request, Group $group)
    {

        $authUser = User::find(authUser()->id);

        $excludedUsers = $this->eventRepository->getPastEventsUsers($group->id);

        $myFriends = $this->searchRepository->getFriendsOf($authUser, $request->event_ids ?? [], $excludedUsers);

        return $this->successResponse(UserSearchResource::collection($myFriends), NULL, Response::HTTP_OK, true);

    }

    public function inviteFriends(Request $request, Group $group)
    {
        try {

            $user = User::find(authUser()->id);

            if (isset($request['all']) && $request['all'] == 'true') {

                $excludedUsers = $this->invitationRepository->getPastEventsUsers($group);

                $friendToBeInvited = $this->searchRepository->getFriendsOf($user, [], $excludedUsers)->pluck('id')->toArray();

                $friendInvited =  $this->invitationRepository->inviteFriends($friendToBeInvited, $group, authUser()->id);

                return $this->successResponse($friendInvited, null, Response::HTTP_OK);
            }

            $friends =  $this->invitationRepository->inviteFriends($request->friend_ids, $group, authUser()->id);

            return $this->successResponse($friends, null, Response::HTTP_OK);
        } catch (\Exception $e) {

            \Log::error($e);

            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

}
