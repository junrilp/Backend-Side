<?php

namespace App\Http\Controllers\Web\Page;

use App\Enums\SearchMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserSearchResource2 as UserSearchResource;
use App\Repository\Browse\BrowseRepository;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BrowseController extends Controller
{
    public $browseRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;

    private $fallBack = ['keyword' => ''];


    public function __construct(BrowseRepository $browseRepository)
    {
        $this->browseRepository = $browseRepository;
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index(Request $request)
    {
        if (Auth::check()) {
            return $this->indexAuth($request);
        }

        return $this->indexGuest($request);
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    private function indexGuest(Request $request)
    {
        $search = $this->searchGuestWithFallback($request->all());

        return Inertia::render('BrowseGuest', [
            'defaultValues' => [],
            'results' => $search['results'] ?? [],
            'hasMorePages' => $search['results']->hasMorePages(),
            'keyword' => $request->keyword,
            'fallback' => $search['fallback']
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    private function indexAuth(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());
        $searchUserPreference = $this->browseRepository->loadUserPreference($request->all(), auth()->id());

        $newestMembers  = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->withLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::NEWEST_MEMBERS
            )
        );

        $recentlyActive = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->withLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::RECENTLY_ACTIVE
            )
        );

        $matches        = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchUserPreference,
                $this->withLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::MATCHES
            )
        );

        $allMembers     = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::ALL_MEMBERS
            )
        );

        $hasMatches = $matches->resource->all() != [];

        if (!$hasMatches) {
            $searchFallback = $this->browseRepository->searchFilter($this->fallBack);

            $allMembers     = UserSearchResource::collection(
                $this->browseRepository->elasticSearchBrowse(
                    $searchFallback,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::ALL_MEMBERS
                )
            );
        }

        $search = $this->searchGuestWithFallback($request->all());

        return Inertia::render('BrowseAuth', [
            'defaultValues' => [],
            'newestMembers' => $newestMembers ?? [],
            'recentlyActive' => $recentlyActive ?? [],
            'matches' => $matches ?? [],
            'allMembers' => $allMembers ?? [],
            'hasMorePages' => $allMembers->hasMorePages(),
            'title' => '- Browse',
            'keyword' => $request->keyword,
            'results' => $search['results'] ?? [],
            'fallback' => $search['fallback']
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function newestMembers(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());

        $newestMembers  = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::NEWEST_MEMBERS
            )
        );

        return Inertia::render('Browse/NewestMembers', [
            'defaultValues' => [],
            'newestMembers' => $newestMembers ?? [],
            'hasMorePages' => $newestMembers->hasMorePages(),
            'title' => '- Browse Newest Members'
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function recentlyActive(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());

        $recentlyActive = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::RECENTLY_ACTIVE
            )
        );

        return Inertia::render('Browse/RecentlyActive', [
            'defaultValues' => [],
            'recentlyActive' => $recentlyActive ?? [],
            'hasMorePages' => $recentlyActive->hasMorePages(),
            'title' => '- Browse Recently Active'
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function matches(Request $request)
    {
        $searchFilter = $this->browseRepository->loadUserPreference($request->all(), auth()->id());

        $matches    = UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::MATCHES
            )
        );

        return Inertia::render('Browse/Matches', [
            'defaultValues' => $matches,
            'matches' => $matches ?? [],
            'hasMorePages' => $matches->hasMorePages(),
            'title' => '- Browse Matches'
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Inertia\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function allMembers(Request $request)
    {
        $searchFilter = $this->browseRepository->searchFilter($request->all(), auth()->id());

        try {
            $allMembers =   UserSearchResource::collection(
                $this->browseRepository->elasticSearchBrowse(
                    $searchFilter,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::ALL_MEMBERS
                )
            );
        } catch (\Throwable $th) {
            Log::alert($th);

            $allMembers =   UserSearchResource::collection(
                $this->browseRepository->laravelSearch(
                    $searchFilter,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::ALL_MEMBERS,
                    []
                )
            );
        }

        return Inertia::render('Browse/AllMembers', [
            'defaultValues' => [],
            'allMembers' => $allMembers ?? [],
            'hasMorePages' => $allMembers->hasMorePages(),
            'title' => '- Browse All Members'
        ]);
    }

    private function searchGuestWithFallback($data)
    {
        $data = [
            'results' => $this->getGuestSearchResults($data),
            'fallback' => false
        ];

        // Check if empty and get fallback data
        if ($data['results']->resource->all() == []) {
            $data = [
                'results' => $this->getGuestSearchFallback(),
                'fallback' => true
            ];
        }

        return $data;
    }

    private function getGuestSearchResults($data)
    {
        $searchFilter = $this->browseRepository->searchFilter($data);

        return UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::ALL_MEMBERS
            )
        );
    }

    private function getGuestSearchFallback()
    {
        $searchFilter = $this->browseRepository->searchFilter($this->fallBack);

        return UserSearchResource::collection(
            $this->browseRepository->elasticSearchBrowse(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::ALL_MEMBERS
            )
        );
    }
}
