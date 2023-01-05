<?php

namespace App\Http\Controllers\Web\Page;

use App\Enums\SearchMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\GroupResource;
use App\Http\Resources\UserSearchResource2 as UserSearchResource;
use App\Models\User;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Connection\ConnectionRepository;
use Carbon\Carbon;
use App\Http\Requests\ConnectionRequest;
use DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConnectionController extends Controller
{
    private $connection;
    private $browseRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;

    public function __construct(ConnectionRepository $connection, BrowseRepository $browseRepository)
    {
        $this->connection = $connection;
        $this->browseRepository = $browseRepository;
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
    }

    public function index(ConnectionRequest $request)
    {
        $requestArray = $request->all();

        $searchFilter = $this->browseRepository->searchFilter($requestArray);

        $birthdayCelebrants = $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::ALL_MEMBERS
        );

        $events = $this->connection->getEventConnection($request->all());

        $bday = authCheck() ? authUser()->birth_date : NULL;

        $groups = $this->connection->getGroupConnection($request->all(), $bday);

        return Inertia::render('Connections', [
            'title' => '- Spotlight',
            'meta' => "Find your Perfect Friends Birthday Twin",
            'defaultValues' => [
                'date_range' => explode(' to ', $requestArray['month_day_range'] ?? ''),
            ],
            'currentDate' => Carbon::now()->format('Y-m-d'),
            'birthdayCelebrants' => UserSearchResource::collection($birthdayCelebrants),
            'events' => EventResource::collection($events),
            'groups' => GroupResource::collection($groups)
        ]);
    }

    public function birthdayCelebrants(ConnectionRequest $request)
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

        // $birthdayCelebrants = $this->connection->getBirthdayCelebrants($requestArray);

        // dd($birthdayCelebrants);

        return Inertia::render('Connections/BirthdayCelebrants', [
            'title' => '- Birthday Twins',
            'defaultValues' => [
                'date_range' => explode(' to ', $requestArray['month_day_range'] ?? ''),
            ],
            'hasMorePages' => $birthdayCelebrants->hasMorePages(),
            'currentDate' => Carbon::now()->format('Y-m-d'),
            'birthdayCelebrants' => UserSearchResource::collection($birthdayCelebrants),
        ]);
    }
}
