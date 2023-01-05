<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\GeneralStatus;
use Throwable;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Group;
use App\Enums\TakeAction;
use App\Models\UserGroup;
use App\Enums\SearchMethod;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Events\UserDiscussionEvent;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\GroupResource;
use App\Http\Resources\GroupNameResource;
use App\Http\Resources\GroupUserResource;
use App\Repository\Group\GroupRepository;
use App\Http\Resources\GroupAdminResource;
use App\Http\Resources\GroupMediaResource;
use App\Http\Resources\PastInviteResource;
use App\Http\Resources\UserSearchResource;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Search\SearchRepository;
use App\Http\Resources\GroupCategoryResource;
use App\Http\Resources\GroupDescriptionResource;
use App\Repository\Invitation\InvitationRepository;

class GroupController extends Controller
{
    use ApiResponser;

    public $browseRepository;
    private $groupRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;

    public function __construct(BrowseRepository $browseRepository, GroupRepository $groupRepository)
    {
        $this->browseRepository = $browseRepository;
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
        $this->groupRepository = $groupRepository;
    }

    public function statistics()
    {
        $group = Group::withTrashed()->get();
        $active = $group->where('status', GeneralStatus::PUBLISHED)->count();
        $deactivated = $group->where('status', GeneralStatus::DEACTIVATED)->count();
        $unpublished = $group->where('status', GeneralStatus::UNPUBLISHED)->count();
        $flagged = $group->where('status', GeneralStatus::FLAGGED)->count();
        $suspended = $group->where('status', GeneralStatus::SUSPENDED)->count();
        $deleted = $group->where('status', GeneralStatus::DELETED)->count();
        return $this->successResponse([
            'statistics' => [
                'Active' => $active,
                'Deactivated' => $deactivated,
                'Unpublished' => $unpublished,
                'Flagged' => $flagged,
                'Suspended' => $suspended,
                'Deleted' => $deleted
            ],
            'all' => $group->count()
        ]);
    }

    public function getGroup(Request $request)
    {
        $perPage = $request->perPage ?? 25;
        $allMembers = '';
        if (isset($request->type) && $request->type === 'advance_search') {
            $searchFilter = $this->browseRepository->searchFilter($request->all());

            $allMembers = $this->browseRepository->elasticSearchAdminUser(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::ALL_MEMBERS
            );
        }

        $group = Group::with(['user', 'media', 'groupNotes']);

        if (!in_array(trim(strtolower($request->page_type)), [TakeAction::EDIT])) {
            // return deleted items if not in array condition
            $group->withTrashed();
        }

        $group->when($request->type === 'header_filter' && $request->search_field !== 'null', function ($q) use ($request) {
            return $q->where('name', 'like', '%' . $request->search_field . '%');
        })
            ->when($request->type === 'header_filter' && $request->city_state !== 'null', function ($q) use ($request) {
                return $q->where('description', 'like', '%' . $request->city_state . '%');
            })
            ->when($request->type === TakeAction::ACTIVE, function ($q) {
                return $q->where('status', GeneralStatus::PUBLISHED);
            })
            ->when((int)$request->type === GeneralStatus::DEACTIVATED, function ($q) {
                return $q->where('status', GeneralStatus::DEACTIVATED);
            })
            ->when((int)$request->type === GeneralStatus::UNPUBLISHED, function ($q) {
                return $q->where('status', GeneralStatus::UNPUBLISHED);
            })
            ->when((int)$request->type === GeneralStatus::FLAGGED, function ($q) {
                return $q->where('status', GeneralStatus::FLAGGED);
            })
            ->when((int)$request->type === GeneralStatus::SUSPENDED, function ($q) {
                return $q->where('status', GeneralStatus::SUSPENDED);
            })
            ->when((int)$request->type === GeneralStatus::DELETED, function ($q) {
                return $q->where('status', GeneralStatus::DELETED);
            })
            ->when($request->type === TakeAction::DEACTIVATE, function ($q) {
                return $q->active();
            })
            ->when($request->type === TakeAction::DELETE, function ($q) {
                return $q->whereNull('deleted_at');
            })
            ->when($request->type === TakeAction::REACTIVATE, function ($q) {
                return $q->inactive();
            })
            ->when($request->type === TakeAction::SUSPEND, function ($q) {
                return $q->activeAndFlagged();
            })
            ->when(!empty($allMembers), function ($q) use ($allMembers) {
                return $q->whereIn('user_id', $allMembers->pluck('id'));
            });

        $groupList = $group->paginate($perPage);

        return $this->successResponse(GroupResource::collection($groupList), '', Response::HTTP_OK, true);
    }


    public function getUserGroupStatistics($userId)
    {

        $userGroup = UserGroup::with('group')->where('user_id', $userId);
        $all = $userGroup->count();
        $active = $userGroup->whereHas('group', function ($builder) {
            $builder->where('published_at', '!=', NULL)->where('status', NULL);
        })->count();

        $unpublished = UserGroup::with('group')->where('user_id', $userId)
            ->whereHas('group', function ($builder) {
                $builder->whereNull('published_at');
            })->count();

        $flagged = UserGroup::with('group')->where('user_id', $userId)
            ->whereHas('group', function ($builder) use ($userId) {
                $builder->where('status', GeneralStatus::FLAGGED);
            })->count();
        $suspended = UserGroup::with('group')->where('user_id', $userId)
            ->whereHas('group', function ($builder) use ($userId) {
                $builder->where('status', GeneralStatus::SUSPENDED);
            })->count();
        $deactivated = UserGroup::with('group')->where('user_id', $userId)
            ->whereHas('group', function ($builder) use ($userId) {
                $builder->where('status', GeneralStatus::DEACTIVATED);
                // ->where('user_id', $userId);
            })->count();
        $deleted = $userGroup->whereHas('group', function ($builder) {
            $builder->whereNotNull('deleted_at');
        })->count();

        $statistics = [
            'All' => $all,
            'active' => $active,
            'deactivated' => $deactivated,
            'unpublished' => $unpublished,
            'flagged' => $flagged,
            'suspended' => $suspended,
            'deleted' => $deleted
        ];

        return $this->successResponse($statistics);
    }

    public function getUserGroups($userId, Request $request)
    {
        try {
            $whereRaw = '(user_id = ' . $userId . ')';

            if (!isset($request->advance_search) or !isset($request->custom_search)) {
                if (!isset($request->advance_search)) {
                    $keywords = $request->get('keyword');
                    $options = $request->all();
                }
                if (!isset($request->custom_search)) {
                    $options['city'] = $request->city_state;
                    $options['state'] = $request->city_state;
                }
                $userGroups = $this->groupRepository->searchUserGroups($userId, $keywords, $options);
                $whereRaw = '((user_id = ' . $userId . ') AND (group_id IN (' . str_replace(array('[', ']'), '', $userGroups->pluck('id')) . ')))';
            }

            $userGroup = UserGroup::with(['group', 'report'])
                ->when($request->type === TakeAction::ACTIVE, function ($q) {
                    return $q->whereHas('group', function ($builder) {
                        $builder->where('published_at', '!=', NULL)->where('status', NULL);
                    });
                })
                ->when($request->type === GeneralStatus::UNPUBLISHED, function ($q) {
                    return $q->whereHas('group', function ($builder) {
                        $builder->whereNull('published_at');
                    });
                })
                ->when($request->type === GeneralStatus::DELETED, function ($q) {
                    return $q->whereHas('group', function ($builder) {
                        $builder->whereNotNull('deleted_at');
                    });
                })
                ->when($request->type === GeneralStatus::FLAGGED, function ($q) {
                    return $q->whereHas('group', function ($builder) {
                        $builder->where('status', 'flag');
                    });
                })
                ->when($request->type === GeneralStatus::SUSPENDED, function ($q) {
                    return $q->whereHas('group', function ($builder) {
                        $builder->where('status', GeneralStatus::SUSPENDED);
                    });
                })
                ->when($request->type === GeneralStatus::DEACTIVATED, function ($q) {
                    return $q->whereHas('group', function ($builder) {
                        $builder->where('status', GeneralStatus::DEACTIVATED);
                    });
                })
                ->whereRaw($whereRaw)
                ->get();

            return $this->successResponse(GroupUserResource::collection($userGroup));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something is wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getGroupBySlug(Request $request)
    {
        $group = Group::where('slug', $request->slug)->withTrashed()->first();

        $subpages = [
            [
                'link' => "/group/$group->slug",
                'label' => 'Members',
            ],
            [
                'link' => "/group/$group->slug/wall",
                'label' => 'Wall',
            ],
            [
                'link' => "/group/$group->slug/discussion-board",
                'label' => 'Discussion Board',
            ],
            [
                'link' => "/group/$group->slug/albums",
                'label' => 'Albums',
                'count' => $group->albums_count,
            ],
        ];

        return $this->successResponse(['group' => new GroupResource($group), 'subpages' => $subpages], 'Group successfully show.', Response::HTTP_ACCEPTED);
    }

    // Robert Hughes
    public function previewGroup(Request $request, Group $group)
    {
        $group->load([
            'members',
            'media',
            'video'
        ]);

        return $this->successResponse(
            new GroupResource($group),
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }

    public function editName(Group $group)
    {
        $formData = new GroupNameResource($group);

        return $this->successResponse(
            [
                'id' => $group->id,
                'slug' => $group->slug,
                'form' => $formData,
                'isPublished' => (bool) $group->is_published,
                'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditCategoryInterest', ['group' => $group]),
            ],
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }


    public function editCategoryInterest(Group $group)
    {
        $formData = new GroupCategoryResource($group);

        return $this->successResponse(
            [
                'id' => $group->id,
                'slug' => $group->slug,
                'form' => $formData,
                'isPublished' => (bool) $group->is_published,
                'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditDescription', ['group' => $group]),
            ],
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editDescription(Group $group)
    {
        $formData = new GroupDescriptionResource($group);

        return $this->successResponse(
            [
                'id' => $group->id,
                'slug' => $group->slug,
                'form' => $formData,
                'isPublished' => (bool) $group->is_published,
                'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditMedia', ['group' => $group]),
            ],
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editMedia(Group $group)
    {
        $formData = new GroupMediaResource($group);

        return $this->successResponse(
            [
                'id' => $group->id,
                'slug' => $group->slug,
                'form' => $formData,
                'isPublished' => (bool) $group->is_published,
                'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditAdmin', ['group' => $group]),
            ],
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editAdmin(Group $group)
    {
        $formData = new GroupAdminResource($group);

        return $this->successResponse(
            [
                'id' => $group->id,
                'slug' => $group->slug,
                'form' => $formData,
                'isPublished' => (bool) $group->is_published,
                'nextPageUrl' => route('groupReviewAndPublish', ['group' => $group]),
            ],
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function reviewAndPublish(Request $request, Group $group)
    {
        $userId = authUser()->id ?? 0;
        $group = Group::query()
            ->withAll()
            ->findOrFail($group->id);

        $groupInfo = (new GroupResource($group))->setAuthUserId($userId);

        return $this->successResponse(
            $groupInfo,
            'Group successfully show.',
            Response::HTTP_ACCEPTED
        );
    }

    public function publishGroup(Request $request, Group $group)
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

    public function publishSuccessful(Request $request, Group $group)
    {
        // if (!(bool) $group->is_published) {
        //     return redirect("/groups/{$group->slug}/edit");
        // }

        $request->session()->flash('publishedJustNowGroup', $group->id);

        return $this->successResponse(
            [
                'id' => $group->id,
                'slug' => $group->slug,
                'isPublished' => (bool) $group->is_published,
            ],
            'Group publish successfully.',
            Response::HTTP_OK,
            true
        );
    }
    public function invitePastEvents(InvitationRepository $invitationRepository, Request $request, Group $group)
    {

        $perPage = 10;
        $keyword = $request->keyword;
        $user = User::find(authUser()->id);

        $result = $invitationRepository->listInvitePastEvents($user, $keyword, $perPage);

        return $this->successResponse(
            PastInviteResource::collection($result),
            'Group invite past events show.',
            Response::HTTP_OK,
            true
        );
    }
    public function inviteFriends(Request $request, InvitationRepository $invitationRepository, SearchRepository $searchRepository, Group $group)
    {
        $authUser = User::find(authUser()->id);

        $excludedUsers = $invitationRepository->getPastEventsUsers($group); //get all the invited users

        //get all friends excluding the invited users
        $myFriends = $searchRepository->getFriendsOf($authUser, $request->event_ids ?? [], $excludedUsers, 10);

        return $this->successResponse(
            UserSearchResource::collection($myFriends),
            'Group invite friends show.',
            Response::HTTP_OK,
            true
        );
    }
    public function deleteGroup($id)
    {
        $group = Group::whereId($id)->first();
        $group->update(['status' => NULL]);
        return $group->delete();
    }

    public function reactivateGroup($id)
    {
        return Group::whereId($id)->update(['status' => NULL]);
    }

    public function publishedGroup($id)
    {
        return Group::whereId($id)->update(['published_at' => Carbon::now(), 'deleted_at' => NULL]);
    }
}
