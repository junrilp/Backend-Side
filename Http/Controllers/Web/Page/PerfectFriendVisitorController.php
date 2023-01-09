<?php

namespace App\Http\Controllers\Web\Page;

use App\Repository\Event\EventRepository;
use App\Repository\Group\GroupRepository;
use App\Http\Controllers\Web\PerfectFriendController;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Http\Resources\FriendResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserSearchResource;
use App\Models\User;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Friend\FriendRepository;
use App\Repository\Profile\ProfileRepository;
use App\Repository\Favorite\FavoriteRepository;
use App\Http\Resources\GroupResourceCollection;
use App\Http\Resources\EventResource;
use App\Enums\AccountType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Perfect Friend" pages of other user
 */
class PerfectFriendVisitorController extends PerfectFriendController
{
    private $profileRepository;
    private $groupRepository;
    private $eventRepository;

    /**
     * @param Request $request
     * @param ProfileRepository $profileRepository
     */
    public function __construct(Request $request, ProfileRepository $profileRepository, GroupRepository $groupRepository, EventRepository $eventRepository)
    {
        parent::__construct();
        $this->profileRepository = $profileRepository;
        $this->groupRepository   = $groupRepository;
        $this->eventRepository   = $eventRepository;
        $this->setNavLinkBase('/' . $request->username);
        $this->setWallNavLink('/wall');
        $this->setProfileNavLink('');
        $this->setFriendsNavLink('/friends');
        $this->setEventsNavLink('/events');
        $this->setEventsMyEventsNavLink('/events');
        $this->setEventsAttendingToNavLink('/events/attending');
        $this->setFavoritesNavLink('/favorites');
        $this->setFavoritesMyFavoritesNavLink('/favorites');
        $this->setFavoritesFavoritedMeNavLink('/favorites/favorited-me');
        $this->setGroupsNavLink('/groups');
        $this->setGroupsMyGroupsNavLink('/groups');
        $this->setGroupsMyAllGroupsNavLink('/groups/all');

        /*
         * Redirect to your own PF link if it's your username
         */
        $this->middleware(function ($request, $next) {
            if (isMyUsername($request->username)) {
                return redirect('/');
            }

            return $next($request);
        })->only('wall');

        $this->middleware(function ($request, $next) {
            if (isMyUsername($request->username)) {
                return redirect('/my-profile');
            }

            return $next($request);
        })->only('profile');

        $this->middleware(function ($request, $next) {
            if (isMyUsername($request->username)) {
                return redirect('/my-friends');
            }

            return $next($request);
        })->only('friends');

        $this->middleware(function ($request, $next) {
            if (isMyUsername($request->username)) {
                return redirect('/my-events');
            }

            return $next($request);
        })->only('events');

        $this->middleware(function ($request, $next) {
            if (isMyUsername($request->username)) {
                return redirect('/my-events/attending');
            }

            return $next($request);
        })->only('attendingEvents');

        $this->middleware(function ($request, $next) {
            if (isMyUsername($request->username)) {
                return redirect('/my-qr-code');
            }

            return $next($request);
        })->only('qrCode');
    }

    /**
     * Other user's wall page
     *
     * @param FriendRepository $friendRepository
     * @param string $username
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function wall(FriendRepository $friendRepository, string $username): Response
    {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();

        $friends = FriendResource::collection($friendRepository::getFriends($user->id));

        $meta = 'See photos and videos from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        // get the groups of the user that he/she joined
        $groups = (new GroupResourceCollection(
                        $this->groupRepository->searchUserGroups(
                            $user->id,
                            null,
                            ['type' => 'joined', 'perPage' => 6]
                        )
                    )
                )->setAuthUserId(authUser()->id ?? 0);

        // get the events of the user that he/she joined
        $events = EventResource::collection($this->eventRepository->getMyEvents(
            $user->id,
            ['perPage' => 6, 'type' => 'interested'],
            true)
        );

        return Inertia::render('PerfectFriend/Visitor/Wall', $this->baseResponseData->merge([
            'user' => $user,
            'friends' => $friends,
            'groups' => $groups,
            'events' => $events,
            'title' => '- ' . $user->first_name . '\'s Wall',
            'meta' => $meta
        ]));
    }

    /**
     *Other user's profile page
     *
     * @param string $username
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function profile(string $username): Response
    {

        $user = User::where('user_name', '=', $username)
            ->firstOrFail();
        $userProfile = $this->profileRepository->getProfile($user);

        $meta = 'See photos and videos from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
        $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        return Inertia::render('PerfectFriend/Visitor/Profile',
            $this->baseResponseData->merge([
                'profile' => new ProfileResource($userProfile),
                'title'   => $this->checkIfInfluencer($user) . ' - ' . $user->first_name . ' ' . $user->last_name,
                'meta' => $meta
            ]));
    }

    /**
     *Other user's friends page
     *
     * @param Request $request
     * @param BrowseRepository $browseRepository
     * @param FriendRepository $friendRepository
     * @param string $username
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function friends(
        Request $request,
        BrowseRepository $browseRepository,
        FriendRepository $friendRepository,
        string $username
    ): Response {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();
        $friends = FriendResource::collection($friendRepository::getFriends($user->id));

        $friendsCount = $friendRepository::getFriendsCount($user->id);

        $meta = 'See Friends from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        return Inertia::render('PerfectFriend/Visitor/Friends',
            $this->baseResponseData->merge([
                'friends' => $friends ?? [],
                'friendCount' => $friendsCount,
                'username' => $username,
                'userId' => $user->id,
                'friendshipStatus' => $user->friendshipStatus,
                'defaultValues' => null,
                'hasMorePages' => $friends->hasMorePages(),
                'title' => $user->first_name . '\'s Friends',
                'meta' => $meta
            ]));
    }

    /**
     * Users events page
     *
     * @param string $username
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function events(string $username): Response
    {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();

        $meta = 'See events and huddles from Perfect Friends ' .  $this->checkIfInfluencer($user) . $user->first_name . ' ' .
        $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        return Inertia::render('PerfectFriend/Visitor/Events/MyEvents', $this->baseResponseData->merge([
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
            'userId' => $user->id,
            'title' => ($user->is_influencer ? 'Influencer - ' : '- ').$user->full_name.' Events and Huddles',
            'meta' => $meta
        ]));
    }

    /**
     * Users attending events page
     *
     * @param string $username
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function attendingEvents(string $username): Response
    {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();

        $meta = 'See events and huddles from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ') will be attending';

        return Inertia::render('PerfectFriend/Visitor/Events/AttendingEvents', $this->baseResponseData->merge([
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
            'userId' => $user->id,
            'title' => ($user->is_influencer ? 'Influencer - ' : '- ').$user->full_name.' attending other Events and Huddles',
            'meta' => $meta
        ]));
    }

    /**
     * Other user's QR Code page
     *
     * @param string $username
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function qrCode(string $username): Response
    {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();
        $userProfile = $this->profileRepository->getProfile($user);

        return Inertia::render('PerfectFriend/Visitor/QrCode', [
            'profile' => new ProfileResource($userProfile),
            'title' => '- '.$user->first_name . '\'s QR Code'
        ]);
    }

    /**
     * User favorites
     *
     * @param string $username
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function favorites(string $username): Response {
        $user = User::where('user_name', '=', $username)
            ->firstOrFail();

        $myFavorites = UserSearchResource::collection(FavoriteRepository::myFavorites($user->id));

        $meta = 'See Favorites from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        return Inertia::render(
            'PerfectFriend/Visitor/Favorites/MyFavorites',
            $this->baseResponseData->merge([
                'fields' => [
                    'keyword'
                ],
                'myFavorites' => $myFavorites ?? [],
                'defaultValues' => null,
                'hasMorePages' => $myFavorites->hasMorePages(),
                'title' => ($user->is_influencer ? 'Influencer - ' : '- ').$user->full_name.' Favorites',
                'meta' => $meta
            ])
        );
    }

    /**
     * Get who favorited me
     *
     * @param string $username
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function favoritedMe(string $username) {

        $user = User::where('user_name', '=', $username)
                ->firstOrFail();
        $favoritedMe = UserSearchResource::collection(FavoriteRepository::favoritedMe($user->id));

        $meta = 'See Favorites from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';


        return Inertia::render(
            'PerfectFriend/Visitor/Favorites/FavoritedMe',
            $this->baseResponseData->merge([
                'favoritedMe' => $favoritedMe ?? [],
                'defaultValues' => null,
                'hasMorePages' => $favoritedMe->hasMorePages(),
                'title' => 'Favorited '.($user->is_influencer ? 'Influencer - ' : '- ').$user->full_name,
                'meta' => $meta
            ])
        );
    }

    /**
     * User Create Groups
     *
     * @param Request $request
     * @param string $username
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function groups(Request $request, string $username): Response {
        $user = User::where('user_name', '=', $username)
                ->firstOrFail();
        $keyword = $request->keyword;
        $options = [
            'type' => 'my-groups',
            'perPage' => 15,
            'relations' => [
                'media',
                'members' => function (BelongsToMany $builder) {
                    $builder->inRandomOrder(now()->toDateString());
                }
            ]
        ];

        $groups = (new GroupResourceCollection(
                $this->groupRepository->searchUserGroups(
                    $user->id,
                    $keyword,
                    $options
                )
            )
        )->setAuthUserId($user->id ?? 0);

        $meta = 'See Groups from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        return Inertia::render('PerfectFriend/Visitor/Groups/MyGroups', $this->baseResponseData->merge([
            'userGroups' => $groups ?? [],
            'title' => ($user->is_influencer ? 'Influencer - ' : '- ').$user->full_name.' Groups',
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
    public function allGroups(Request $request, string $username): Response {
        $user = User::where('user_name', '=', $username)
                ->firstOrFail();
        $keyword = $request->keyword;
        $options = [
            'type' => 'all',
            'perPage' => 15,
            'relations' => [
                'media',
                'members' => function (BelongsToMany $builder) {
                    $builder->inRandomOrder(now()->toDateString());
                }
            ]
        ];

        $groups = (new GroupResourceCollection(
            $this->groupRepository->searchUserGroups(
                    $user->id,
                    $keyword,
                    $options
                )
            )
        )->setAuthUserId($user->id ?? 0);

        $meta = 'See Groups from Perfect Friends ' . $this->checkIfInfluencer($user) . $user->first_name . ' ' .
            $user->last_name . ' (https://perfectfriends.com/' . $user->user_name . ')';

        return Inertia::render('PerfectFriend/Visitor/Groups/AllGroups', $this->baseResponseData->merge([
            'allGroups' => $groups,
            'title' => ($user->is_influencer ? 'Influencer - ' : '- ').$user->full_name.' Groups',
            'meta' => $meta
        ]));

    }

    protected function checkIfInfluencer($user)
    {
        if($user){

            return $user->account_type === AccountType::PREMIUM ? "Influencer " : "";

        }

        return '';

    }
}
