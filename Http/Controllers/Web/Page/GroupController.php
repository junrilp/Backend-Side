<?php

namespace App\Http\Controllers\Web\Page;

use App\Enums\DiscussionType;
use App\Events\UserDiscussionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditGroupRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\GroupCategoryResource;
use App\Http\Resources\GroupDescriptionResource;
use App\Http\Resources\GroupMediaResource;
use App\Http\Resources\GroupAdminResource;
use App\Http\Resources\GroupNameResource;
use App\Http\Resources\GroupResource2;
use App\Http\Resources\GroupResource;
use App\Http\Resources\Media as MediaResource;
use App\Http\Resources\PastInviteResource;
use App\Http\Resources\GroupMemberResource;
use App\Http\Resources\UserSearchResource;
use App\Models\Category;
use App\Models\Group;
use App\Models\Type;
use App\Models\User;
use App\Repository\Album\AlbumRepository;
use App\Repository\Group\GroupRepository;
use App\Repository\Invitation\InvitationRepository;
use App\Repository\Search\SearchRepository;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    private $groupRepository;

    public function __construct(GroupRepository $groupRepository)
    {
        $this->groupRepository = $groupRepository;
    }

    /**
     * Index Page
     *
     * @return response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function index(): Response
    {
        return Inertia::render('Groups/Index', ['title' => '- Explore and discover new groups', 'meta' => 'Explore and discover new Perfect Friends Groups']);
    }

    /**
     * Create group page
     *
     * @return Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function create(): Response
    {
        return Inertia::render('Groups/Create', array_merge($this->getCommonData(), ['title' => '- Create group']));
    }

    /**
     * @return array
     *
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    private function getCommonData(): array
    {
        return [
            'groupTypes' => Type::query()
                ->where('status', 1)
                ->where('group_enable', 1)
                ->get(['id', 'name'])->toArray(),
            'categories' => Category::query()
                ->where('status', 1)
                ->where('group_enable', 1)
                ->get(['id', 'name'])->toArray(),
        ];
    }

    private function getGroupDetails(Group $group)
    {
        return collect([
            'title' => function () use ($group) {
                return '- '.$group->name;
            },
            'meta' => function () use ($group) {
                $membersCount = $group->total_members;
                $metaDescription = substr($group->description, 0, 120);

                $elipsis = strlen($metaDescription)==120 ? '...' : '';

                return "Perfect Friends Group - $group->name has $membersCount members $metaDescription$elipsis";

            },
            'bannerPhoto' => function () use ($group) {
                return (new MediaResource($group->media))
                    ->response()
                    ->getData()
                    ->data
                    ->sizes
                    ->lg;
            },
            'bannerBackground' => function () use ($group) {
                return '/images/event-single-cover.png';
            },
            'group' => function () use ($group) {
                $group->load([
                    'members',
                    'media',
                    'video',
                    'user',
                ]);

                return json_decode(response((new GroupResource($group))->setAuthUserId(authUser()->id ?? 0))->content(), true);
            },
            'subPages' => function () use ($group) {
                return [
                    [
                        'link' => "/groups/$group->slug",
                        'label' => 'Members',
                    ],
                    [
                        'link' => "/groups/$group->slug/wall",
                        'label' => 'Wall',
                    ],
                    [
                        'link' => "/groups/$group->slug/discussion-board",
                        'label' => 'Discussion Board',
                    ],
                    [
                        'link' => "/groups/$group->slug/albums",
                        'label' => 'Albums',
                        'count' => $group->albums_count,
                    ],
                ];
            },
            'unreadChat' => function() use ($group) {
                $userId = authUser()->id ?? 0;
                return
                    $group->conversation ?
                        $group
                            ->conversation
                            ->chats()
                            ->whereRaw("(NOT FIND_IN_SET(?, seen_by) OR seen_by IS NULL)", [$userId])
                            ->count()
                    : 0;
            }
        ]);
    }

    public function members(Request $request, Group $group): Response
    {
        $isJoining = !!$request->get('auto-join');

        //check if the owner is the owner
        if(auth()->check() && $group->user_id === auth()->id()) {
            //if yes, allow access
            //insert other logic here if needed
        } else {
            //check if the page is unpublished
            if (!$group->isPublished()) {
                return Inertia::render('Error', [
                    'status'    => 200,
                    'message'   => 'has been unpublished.',
                    'hideStatus' => true,
                    'title' => '- '.$group->name
                ]);
            }
        }

        // if ($request->get('auto-join') && authCheck()) {
        //     $this->groupRepository->joinUserToGroup( authUser()->id, $group);


        //     if ($request->get('postToWall') === 'true' && authCheck()) {
        //         event(new UserDiscussionEvent(authUser()->id, 'group_joined', $group->makeHidden(['user', 'members', 'media'])));
        //     }
        // }

        return Inertia::render('Groups/Group2', $this->getGroupDetails($group)->merge([
            'content' => function (GroupRepository $groupRepository) use ($request, $group) {
                $keywords = $request->get('keyword');
                $options = $request->except('keyword');
                $members = $groupRepository->searchGroupMembers($group, $keywords, $options);

                    return [
                        'members' => GroupMemberResource::collection($members),
                        'hasMorePages' => $members ? $members->hasMorePages() : false,
                    ];
            },
            'isJoining' => $isJoining
            ])
        );
    }

    public function discussionBoard(Group $group)
    {
        return Inertia::render(
            'Groups/Group2',
            $this->getGroupDetails($group)->merge([
                'content' => function () {
                    return [
                        'discussionBoard' => null,
                    ];
                },
            ])
        );
    }


    public function wall(Group $group): Response
    {
        return Inertia::render(
            'Groups/Group2',
            $this->getGroupDetails($group)->merge([
                'content' => function () {
                    return [
                        'wall' => null,
                    ];
                },
            ])
        );
    }

    /**
     * Returns the album section
     */
    public function albums(Group $group): Response
    {
        return Inertia::render(
            'Groups/Group2',
            $this->getGroupDetails($group)->merge([
                'content' => function () {
                    return [
                        'albums' => null,
                    ];
                },
            ])
        );
    }

    /**
     * Returns the album by id
     */
    public function albumById(Group $group, $albumId): Response
    {
        $album = AlbumRepository::getAlbumById(DiscussionType::GROUPS, $albumId);
        return Inertia::render(
            'Groups/Group2',
            $this->getGroupDetails($group)->merge([
                'content' => function () use ($album) {
                    return [
                        'albums' => null,
                        'album' => new AlbumResource($album),
                    ];
                },
            ])
        );
    }




    /**
     * Group page
     *
     * @param Request $request
     * @param Group $event
     * @return Response
     *
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function show(Request $request, Group $group)
    {
        if (!$group->isPublished() && !(auth()->check() && $group->user_id === auth()->id())) {
            abort(404);
        }

        if ($request->get('auto-join') && authCheck()) {
            $this->groupRepository->joinUserToGroup( authUser()->id, $group);


            if ($request->get('postToWall') === 'true' && authCheck()) {
                event(new UserDiscussionEvent(authUser()->id, 'group_joined', $group->makeHidden(['user', 'members', 'media'])));
            }
        }

        $group->load([
            'members',
            'media',
            'video',
            'user',
        ]);

        $membersCount = $group->total_members;
        $metaDescription = substr($group->description, 0, 120);

        return Inertia::render('Groups/Group', [
            'title' => '- '.$group->name,
            'meta' => "Perfect Friends - $group->name has $membersCount members $metaDescription",
            'group' => json_decode(response((new GroupResource($group))->setAuthUserId(authUser()->id ?? 0))->content(), true),
        ]);
    }

    /**
     * Preview a group (DRAFT status)
     *
     * @param Request $request
     * @param Group $group
     * @return Response
     *
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function previewGroup(Request $request, Group $group): Response
    {
       $group->load([
            'members',
            'media',
            'video'
        ]);

        return Inertia::render('Groups/PreviewGroup', [
            'title' => '- Preview: ' . $group->name,
            'group' => json_decode(response((new GroupResource($group))->setAuthUserId(authUser()->id ?? 0))->content(), true),
        ]);
    }

    /**
     * Edit an event
     *
     * @param EditGroupRequest $request
     * @param Group $event
     * @return Response
     *
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function editGroup(Group $group): RedirectResponse
    {
        return redirect(route('groupReviewAndPublish', ['group' => $group]));
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function editName(Group $group): Response
    {
        $formData = new GroupNameResource($group);

        return Inertia::render('Groups/EditName', [
            'id' => $group->id,
            'slug' => $group->slug,
            'form' => $formData,
            'isPublished' => (bool) $group->is_published,
            'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditCategoryInterest', ['group' => $group]),
        ]);
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function editCategoryInterest(Group $group): Response
    {
        $formData = new GroupCategoryResource($group);

        return Inertia::render('Groups/EditCategoryInterest', [
            'id' => $group->id,
            'slug' => $group->slug,
            'form' => $formData,
            'isPublished' => (bool) $group->is_published,
            'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditDescription', ['group' => $group]),
        ]);
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function editDescription(Group $group): Response
    {
        $formData = new GroupDescriptionResource($group);

        return Inertia::render('Groups/EditDescription', [
            'id' => $group->id,
            'slug' => $group->slug,
            'form' => $formData,
            'isPublished' => (bool) $group->is_published,
            'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditMedia', ['group' => $group]),
        ]);
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function editMedia(Group $group): Response
    {
        $formData = new GroupMediaResource($group);

        return Inertia::render('Groups/EditMedia', [
            'id' => $group->id,
            'slug' => $group->slug,
            'form' => $formData,
            'isPublished' => (bool) $group->is_published,
            'nextPageUrl' => $group->is_published || url()->previous() === route('groupReviewAndPublish', ['group' => $group]) ? route('groupReviewAndPublish', ['group' => $group]) : route('groupEditAdmin', ['group' => $group]),
        ]);
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function editAdmin(Group $group): Response
    {
        $formData = new GroupAdminResource($group);

        return Inertia::render('Groups/EditAdmin', [
            'id' => $group->id,
            'slug' => $group->slug,
            'form' => $formData,
            'isPublished' => (bool) $group->is_published,
            'nextPageUrl' => route('groupReviewAndPublish', ['group' => $group]),
        ]);
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function reviewAndPublish(Group $group): Response
    {
        $formData = new GroupResource2($group);

        return Inertia::render('Groups/ReviewAndPublish', [
            'id' => $group->id,
            'slug' => $group->slug,
            'form' => $formData,
            'isPublished' => (bool) $group->is_published,
        ]);
    }

    /**
     * @param Request $request
     * @param Group $group
     *
     * @return RedirectResponse
     */
    public function publishSuccessful(Request $request, Group $group): RedirectResponse
    {
        if (!(bool) $group->is_published) {
            return redirect("/groups/{$group->slug}/edit");
        }

        $request->session()->flash('publishedJustNowGroup', $group->id);

        return redirect("/groups/{$group->slug}/invite-past-events");
    }

    /**
     * @param Group $group
     *
     * @return Response
     */
    public function invitePastEvents(InvitationRepository $invitationRepository, Request $request, Group $group): Response
    {
        $perPage = 10;
        $keyword = $request->keyword;
        $user = User::find(authUser()->id);

        $result = $invitationRepository->listInvitePastEvents($user, $keyword, $perPage);
        // dd($result);
        return Inertia::render('Groups/InvitePastEvents', [
            'id' => $group->id,
            'slug' => $group->slug,
            'pastEvents' => PastInviteResource::collection($result),
            'publishedJustNow' => $request->session()->get('publishedJustNowGroup') === $group->id,
        ]);
    }

    /**
     *
     * @return Response
     */
    public function inviteFriends(Request $request, InvitationRepository $invitationRepository, SearchRepository $searchRepository, Group $group): Response
    {

        $authUser = User::find(authUser()->id);

        $excludedUsers = $invitationRepository->getPastEventsUsers($group); //get all the invited users

        //get all friends excluding the invited users
        $myFriends = $searchRepository->getFriendsOf($authUser, $request->event_ids ?? [], $excludedUsers, 10);

        return Inertia::render('Groups/InviteFriends', [
            'id' => $group->id,
            'slug' => $group->slug,
            'myFriends' => UserSearchResource::collection($myFriends)
        ]);
    }
}
