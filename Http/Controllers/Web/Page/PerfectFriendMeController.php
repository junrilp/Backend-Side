<?php

namespace App\Http\Controllers\Web\Page;

use App\Enums\SearchMethod;
use App\Enums\AccountType;
use App\Repository\Event\EventRepository;
use App\Repository\Group\GroupRepository;
use App\Http\Controllers\Web\PerfectFriendController;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserSearchResource;
use App\Http\Resources\FriendResource;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Favorite\FavoriteRepository;
use App\Repository\Friend\FriendRepository;
use App\Repository\Profile\ProfileRepository;
use App\Http\Resources\GroupResourceCollection;
use App\Http\Resources\EventResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated User's "Perfect Friend" pages
 */
class PerfectFriendMeController extends PerfectFriendController
{
    private $profileRepository;
    private $groupRepository;
    private $eventRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;

    /**
     * @param ProfileRepository $profileRepository
     */
    public function __construct(ProfileRepository $profileRepository, GroupRepository $groupRepository, EventRepository $eventRepository)
    {
        parent::__construct();
        $this->profileRepository = $profileRepository;
        $this->groupRepository   = $groupRepository;
        $this->eventRepository   = $eventRepository;
        $this->setNavLinkBase('/');
        $this->setWallNavLink('wall');
        $this->setProfileNavLink('my-profile');
        $this->setFavoritesNavLink('my-favorites');
        $this->setFavoritesMyFavoritesNavLink('my-favorites');
        $this->setFavoritesFavoritedMeNavLink('my-favorites/favorited-me');
        $this->setFriendsNavLink('my-friends');
        $this->setFriendsMyFriendsNavLink('my-friends');
        $this->setFriendsFriendRequestsNavLink('my-friends/friend-requests');
        $this->setFriendsSentRequestsNavLink('my-friends/sent-requests');
        $this->setEventsNavLink('my-events');
        $this->setEventsMyEventsNavLink('my-events');
        $this->setEventsAttendingToNavLink('my-events/attending');
        $this->setEventsPastEventsNavLink('my-events/past-events');
        $this->setEventsAdministratorRolesNavLink('my-events/administrator-roles');
        $this->setGroupsNavLink('my-groups');
        $this->setGroupsMyGroupsNavLink('my-groups');
        $this->setGroupsMyAllGroupsNavLink('my-groups/all');
        $this->setGroupsMyGroupsPendingInvintesNavLink('my-groups/pending-invites');
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
    }

    /**
     * Authenticated User's wall page
     *
     * @param FriendRepository $friendRepository
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function wall(FriendRepository $friendRepository): Response
    {
        $friends = FriendResource::collection($friendRepository::getFriends(auth()->id()));

        // get the groups of the user that he/she joined
        $groups = $this->groupRepository->searchUserGroups(
            authUser()->id, null,
            [
                'type' => 'joined',
                'perPage' => 6
            ]
        );

        $events = $this->eventRepository->getMyEvents(
            authUser()->id,
            [
                'perPage' => 6, 'type' => 'interested'
            ],
            true);

        $meta = 'See photos and videos from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
                authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render('PerfectFriend/Me/Wall', $this->baseResponseData->merge([
            'groups' => (new GroupResourceCollection ($groups))->setAuthUserId(authUser()->id ?? 0),
            'events' => EventResource::collection($events),
            'user' => auth()->user(),
            'friends' => $friends,
            'title' => 'My Wall',
            'meta' => $meta
        ]));
    }

    /**
     * Authenticated User's profile page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function profile(): Response
    {
        $user = authUser();
        $userProfile = $this->profileRepository->getProfile($user);


        $meta = 'See photos and videos from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render(
            'PerfectFriend/Me/Profile',
            $this->baseResponseData->merge([
                'profile' => new ProfileResource($userProfile),
                'title'   => $this->checkIfInfluencer() . $user->first_name . ' ' . $user->last_name,
                'meta' => $meta
            ])
        );
    }

    /**
     * Authenticated User's favorites page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function myFavorites(
        Request $request,
        BrowseRepository $browseRepository
    ): Response {
        $searchFilter = $browseRepository->searchFilter($request->all(), auth()->id());

        $myFavoritesIds =  FavoriteRepository::myFavorites(auth()->id(), true);

        $myFavorites    = UserSearchResource::collection(
            BrowseRepository::elasticUserSearch(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::FAVORITES,
            $myFavoritesIds
        )
        );

        $meta = 'See Favorites from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';


        return Inertia::render(
            'PerfectFriend/Me/Favorites/MyFavorites',
            $this->baseResponseData->merge([
                'myFavorites' => $myFavorites ?? [],
                'defaultValues' => null,
                'hasMorePages' => $myFavorites->hasMorePages(),
                'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' Favorites',
                'meta' => $meta

            ])
        );
    }

    /**
     * Authenticated User's favorite me page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function favoritedMe(
        Request $request,
        BrowseRepository $browseRepository
    ): Response {
        $searchFilter = $browseRepository->searchFilter($request->all(), auth()->id());

        $favoritedMeIds =  FavoriteRepository::favoritedMe(auth()->id(), true);

        $favoritedMe  = UserSearchResource::collection(
            BrowseRepository::elasticUserSearch(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::FAVORITES,
            $favoritedMeIds
        )
        );

        $meta = 'See Favorites from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render(
            'PerfectFriend/Me/Favorites/FavoritedMe',
            $this->baseResponseData->merge([
                'favoritedMe' => $favoritedMe ?? [],
                'defaultValues' => null,
                'hasMorePages' => $favoritedMe->hasMorePages(),
                'title' => 'Favorited ' . (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name,
                'meta' => $meta
            ])
        );
    }

    /**
     * Authenticated User's friends page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function myFriends(
        Request $request,
        BrowseRepository $browseRepository,
        FriendRepository $friendRepository
    ): Response {
        $searchFilter = $browseRepository->searchFilter($request->all(), auth()->id());

        $userId = $request->userId != '' ? $request->userId : auth()->id();

        $friendIds = $friendRepository::getFriends($userId, null, $this->perPage, true);

        $myFriends      = UserSearchResource::collection(
            BrowseRepository::elasticUserSearch(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::FRIENDS,
            $friendIds
        )
        );

        $meta = 'See Friends from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render(
            'PerfectFriend/Me/Friends/MyFriends',
            $this->baseResponseData->merge([
                'myFriends' => $myFriends ?? [],
                'friendCount' => count($friendIds),
                'defaultValues' => null,
                'hasMorePages' => $myFriends->hasMorePages(),
                'title' => '- My Friends',
                'meta' => $meta
            ])
        );
    }

    /**
     * Authenticated User's friend requests page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function friendRequests(
        Request $request,
        BrowseRepository $browseRepository,
        FriendRepository $friendRepository
    ): Response {
        $searchFilter = $browseRepository->searchFilter($request->all(), auth()->id());
        $userId = authUser()->id;

        $friendCount = count($friendRepository::getFriends($userId, null, $this->perPage, true));
        $friendIds = $friendRepository::getFriends(authUser()->id, 'requested', $this->perPage, true);

        $myFriendRequests   = UserSearchResource::collection(
            BrowseRepository::elasticUserSearch(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::FRIENDS,
            $friendIds
        )
        );

        $meta = 'See Friends from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render(
            'PerfectFriend/Me/Friends/FriendRequests',
            $this->baseResponseData->merge([
                'friendRequests' => $myFriendRequests ?? [],
                'friendCount' => count($friendIds),
                'defaultValues' => null,
                'hasMorePages' => $myFriendRequests->hasMorePages(),
                'title' => '- My Friend Requests',
                'meta' => $meta
            ])
        );
    }

    /**
     * Authenticated User's sent requests page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function sentRequests(
        Request $request,
        BrowseRepository $browseRepository,
        FriendRepository $friendRepository
    ): Response {
        $searchFilter = $browseRepository->searchFilter($request->all(), auth()->id());
        $userId = authUser()->id;
        $friendCount = count($friendRepository::getFriends($userId, null, $this->perPage, true));
        $friendIds = $friendRepository::getFriends(auth()->id(), 'sent', $this->perPage, true);

        $mySentRequests     = UserSearchResource::collection(
            BrowseRepository::elasticUserSearch(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::FRIENDS,
            $friendIds
        )
        );

        $meta = 'See Friends from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render(
            'PerfectFriend/Me/Friends/SentRequests',
            $this->baseResponseData->merge([
                'sentRequests' => $mySentRequests ?? [],
                'friendCount' => count($friendIds),
                'defaultValues' => null,
                'hasMorePages' => $mySentRequests->hasMorePages(),
                'title' => '- Sent Requests',
                'meta' => $meta
            ])
        );
    }

    /**
     * Users events page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function events(): Response
    {

        $meta = 'See events and huddles from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render('PerfectFriend/Me/Events/MyEvents', $this->baseResponseData->merge([
            'fields' => [
                'keyword',
                'venue',
                'city',
                'state',
                'lat',
                'lng',
                'distance',
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' Events and Huddles',
            'meta' => $meta
        ]));
    }

    /**
     * Users attending events page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function attendingEvents(): Response
    {

        $meta = 'See events and huddles from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ') will be attending';

        return Inertia::render('PerfectFriend/Me/Events/AttendingEvents', $this->baseResponseData->merge([
            'fields' => [
                'keyword',
                'venue',
                'city',
                'state',
                'lat',
                'lng',
                'distance',
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ').authUser()->full_name.' attending other Events and Huddles',
            'meta' => $meta

        ]));
    }

    /**
     * My past events page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function pastEvents(): Response
    {

        $meta = 'See past events and huddles from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render('PerfectFriend/Me/Events/PastEvents', $this->baseResponseData->merge([
            'fields' => [
                'keyword',
                'venue',
                'city',
                'state',
                'lat',
                'lng',
                'distance',
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' past Events and Huddles',
            'meta' => $meta
        ]));
    }

    public function administratorRoles(): Response
    {

        $meta = 'See events and huddles from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ') will be attending';

        return Inertia::render('PerfectFriend/Me/Events/AdministratorRoles', $this->baseResponseData->merge([
            'fields' => [
                'keyword',
                'venue',
                'city',
                'state',
                'lat',
                'lng',
                'distance',
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' administrator role Events and Huddles',
            'meta' => $meta
        ]));
    }
    /**
     * Authenticated User's QR Code page
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function qrCode(): Response
    {
        $userProfile = $this->profileRepository->getProfile(auth()->user());

        return Inertia::render('PerfectFriend/Me/MyQrCode', [
            'profile' => new ProfileResource($userProfile),
            'title' => '- My QR Code'
        ]);
    }

    /**
     * Users groups page
     *
     * @return Response
     *
     *  @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function groups(): Response
    {

        $meta = 'See Groups from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render('PerfectFriend/Me/Groups/MyGroups', $this->baseResponseData->merge([
            'fields' => [
                'keyword'
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' Groups',
            'meta' => $meta
        ]));
    }

    /**
     * Users all page groups
     *
     * @return Response
     *
     *  @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function allGroups(): Response
    {

        $meta = 'See Groups from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render('PerfectFriend/Me/Groups/AllGroups', $this->baseResponseData->merge([
            'fields' => [
                'keyword'
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' Groups',
            'meta' => $meta
        ]));
    }

    /**
     * Users pending group invites
     *
     * @return Response
     *
     *  @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function pendingGroupInvites(): Response
    {

        $meta = 'See Groups from Perfect Friends ' . $this->checkIfInfluencer() . authUser()->first_name . ' ' .
            authUser()->last_name . ' (https://perfectfriends.com/' . authUser()->user_name . ')';

        return Inertia::render('PerfectFriend/Me/Groups/PendingGroupInvites', $this->baseResponseData->merge([
            'fields' => [
                'keyword'
            ],
            'autoSubmitOff' => true,
            'title' => (authUser()->is_influencer ? 'Influencer - ' : '- ') . authUser()->full_name . ' pending Group Invites',
            'meta' => $meta
        ]));
    }

    protected function checkIfInfluencer(){

        return authUser()->account_type === AccountType::PREMIUM ? "Influencer " : "";

    }
}
