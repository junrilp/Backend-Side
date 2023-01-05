<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\GroupResource;
use App\Http\Resources\UserSearchResource2 as UserSearchResource;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Connection\ConnectionRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\SearchMethod;
use App\Http\Requests\ConnectionRequest;

class ConnectionController extends Controller
{
    use ApiResponser;

    private $connection;
    private $browseRepository;

    public function __construct(ConnectionRepository $connection, BrowseRepository $browseRepository)
    {
        $this->connection = $connection;
        $this->browseRepository = $browseRepository;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function getBirthdayCelebrants(ConnectionRequest $request)
    {
        $requestArray = $request->all();

        $searchFilter = $this->browseRepository->searchFilter($requestArray);

        $birthdayCelebrants = $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                false,
                12,
                12,
                SearchMethod::ALL_MEMBERS
        );

        return $this->successResponse(UserSearchResource::collection($birthdayCelebrants), '', Response::HTTP_OK, true);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function getEventConnection(Request $request)
    {

        $events = $this->connection->getEventConnection($request->all());

        return $this->successResponse(EventResource::collection($events), '', Response::HTTP_OK, true);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function getGroupConnection(Request $request)
    {
        $bday = authCheck() ? authUser()->birth_date : NULL;

        $groups = $this->connection->getGroupConnection($request->all(), $bday);

        return $this->successResponse(GroupResource::collection($groups), '', Response::HTTP_OK, true);

    }
}
