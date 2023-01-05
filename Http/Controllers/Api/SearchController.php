<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserSearchStumbleResource;
use App\Enums\SearchMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserSearchResource;
use App\Models\User;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Favorite\FavoriteRepository;
use App\Repository\Friend\FriendRepository;
use App\Repository\Search\SearchRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class SearchController extends Controller
{
    use ApiResponser;

    public $browseRepository;
    public $searchRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;

    public function __construct(BrowseRepository $browseRepository, SearchRepository $searchRepository)
    {
        $this->browseRepository = $browseRepository;
        $this->searchRepository = $searchRepository;
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function matches(Request $request)
    {
        $searchFilter = $this->browseRepository->loadUserPreference($request->all(), auth()->id());

        $results =  $this->browseRepository->elasticSearchBrowse(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::MATCHES
        );

        $response = UserSearchResource::collection($results);

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function newestMembers(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());

        $results =  $this->browseRepository->elasticSearchBrowse(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::NEWEST_MEMBERS
        );

        $response = UserSearchResource::collection($results);

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function recentlyActive(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());

        $results =  $this->browseRepository->elasticSearchBrowse(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::RECENTLY_ACTIVE
        );

        $response = UserSearchResource::collection($results);

        return $response;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function allMembers(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());

        $results =  $this->browseRepository->elasticSearchBrowse(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::ALL_MEMBERS
        );

        if ($request['is_stumble']) {
            $response = UserSearchStumbleResource::collection($results);

            return $response;
        }

        $response = UserSearchResource::collection($results);

        return $response;
    }

    /**
     * API method for listing my favorites with search
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function myFavorites(Request $request)
    {
        try {

            $searchFilter = BrowseRepository::searchFilter($request->all(), auth()->id());

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

            return $this->successResponse($myFavorites, 'Success', Response::HTTP_OK, true);
        } catch (Throwable $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * API method for listing favorited me with search
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function favoritedMe(Request $request)
    {
        try {
            $searchFilter = BrowseRepository::searchFilter($request->all(), auth()->id());

            $favoritedMeIds =  FavoriteRepository::favoritedMe(auth()->id(), true);

            $favoritedMe    = UserSearchResource::collection(
                BrowseRepository::elasticUserSearch(
                    $searchFilter,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::FAVORITES,
                    $favoritedMeIds
                )
            );

            return $this->successResponse($favoritedMe, 'Success', Response::HTTP_OK, true);
        } catch (Throwable $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * API method for listing my friends with search
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function myFriends(Request $request)
    {
        try {
            $searchFilter = BrowseRepository::searchFilter($request->all(), auth()->id());

            $userId = $request->userId != '' ? $request->userId : auth()->id();

            $status = $request->status ?? null;

            $friendIds = FriendRepository::getFriends($userId, $status, $this->perPage, true);

            $myFriends = UserSearchResource::collection(
                BrowseRepository::elasticUserSearch(
                    $searchFilter,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::FRIENDS,
                    $friendIds
                )
            );

            return $this->successResponse($myFriends, 'Success', Response::HTTP_OK, true);
        } catch (Throwable $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function guestSearch(Request $request)
    {

        $searchFilter = $this->browseRepository->searchFilter($request->all());

        $results =  $this->browseRepository->elasticSearchBrowse(
            $searchFilter,
            $this->woutLimit,
            $this->perPage,
            $this->limit,
            SearchMethod::ALL_MEMBERS
        );

        $response = UserSearchResource::collection($results);

        return $response;

    }

    public function getUsers(Request $request)
    {

        $options = array_merge($request->all(), [
            'exclude_users' => [auth()->id()],
        ]);

        $users = $this->searchRepository->getUsers($request->get('keyword'), $options);

        return $this->successResponse(
            UserSearchResource::collection($users),
            'Success',
            Response::HTTP_OK
        );

    }

    public function searchMyFriends(Request $request)
    {

        $authUser = User::find(authUser()->id);

        $users = $this->searchRepository->getFriendsOf($authUser, $request, [], 10);

        return $this->successResponse(
            UserSearchResource::collection($users),
            'Success',
            Response::HTTP_OK,
            true
        );

    }

}
