<?php

namespace App\Repository\Browse;

use App\Enums\AccountType;
use App\Enums\SearchDefault;
use App\Enums\SearchMethod;
use App\Models\AdminUser;
use App\Models\User;
use App\Repository\Browse\BrowseInterface;
use App\Repository\Search\SearchRepository;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class BrowseRepository implements BrowseInterface
{
    use ApiResponser;
    /**
     * @param mixed $request
     * @param mixed $userId
     *
     * @return array $searchFilter
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function loadUserPreference($request, $userId)
    {
        $user = User::with(['profile','preferences'])->find($userId);

        // $smoker             = explode(',', $user->preferences->are_you_smoker);
        // $any_children       = explode(',', $user->preferences->any_children);

        $ageFrom = $user->preferences->age_from && $user->preferences->age_from!='' ? $user->preferences->age_from :  SearchDefault::AGE_FROM;
        $ageTo = $user->preferences->age_to && $user->preferences->age_to!='' ? $user->preferences->age_to :  SearchDefault::AGE_TO;
        $heightFrom = $user->preferences->height_from ?? SearchDefault::HEIGHT_FROM;
        $heightTo = $user->preferences->height_to ?? SearchDefault::HEIGHT_TO;

        if (isset($request['influencer'])) {
            $influencer = $request['influencer']=='true' ? AccountType::PREMIUM : AccountType::NO_SELECTION;
        }


        $searchFilter = [
            'keyword'           => $request['keyword']                  ??  null,
            'month'             => $request['month']                    ??  null,
            'day'               => $request['day']                      ??  null,
            'city'              => $request['city']                     ??  0,
            'state'             => $request['state']                    ??  0,
            'zip_code'          => $request['zip_code']                 ??  0,
            'lat'               => $request['lat']                      ??  $user->preferences->latitude,
            'lng'               => $request['lng']                      ??  $user->preferences->longitude,
            'distance'          => $request['distance']                 ??  100,
            'influencer'        => $influencer                          ??  AccountType::NO_SELECTION,
            'gender'            => $request['gender']                   ??  (array)explode(",", $user->preferences->gender),
            'drinking'          => $request['drinking']                 ??  (array)explode(",", $user->preferences->are_you_drinker),
            'smoking'           => $request['smoking']                  ??  (array)explode(",", $user->preferences->are_you_smoker),
            'ethnicity'         => $request['ethnicity']                ??  (array)explode(",", $user->preferences->ethnicity),
            'zodiac_sign'       => $request['zodiac_sign']              ??  (array)explode(",", $user->preferences->zodiac_sign),
            'body_type'         => $request['body_type']                ??  (array)explode(",", $user->preferences->body_type),
            'relationship_status'   => $request['relationship_status']  ??  (array)explode(",", $user->preferences->relationship_status),
            'children'          => $request['children']                 ??  (array)explode(",", $user->preferences->any_children),
            'income_level'      => $request['income_level']             ??  (array)explode(",", $user->preferences->income_level),
            'education_level'   => $request['education_level']          ??  (array)explode(",", $user->preferences->education_level),
            'age_from'          => $request['age_from']                 ??  $ageFrom,
            'age_to'            => $request['age_to']                   ??  $ageTo,
            'height_from'       => $request['height_from']              ??  $heightFrom,
            'height_to'         => $request['height_to']                ??  $heightTo,
        ];

        return $searchFilter;
    }

    /**
     * @param mixed $request
     *
     * @return array $searchFilter
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function searchFilter($request)
    {
        if (isset($request['influencer'])) {
            $influencer = $request['influencer']=='true' ? AccountType::PREMIUM : AccountType::NO_SELECTION;
        }

        if (!isset($request['age_from']) && isset($request['age_to'])) {
            $request['age_from'] = 18;
        }

        if (isset($request['age_from']) && !isset($request['age_to'])) {
            $request['age_to'] = 115;
        }

        if (!isset($request['height_from']) && isset($request['height_to'])) {
            $request['height_from'] = 55;
        }

        if (isset($request['height_from']) && !isset($request['height_to'])) {
            $request['height_to'] = 89;
        }

        if (isset($request['date'])) {
            $birthDate = Carbon::createFromFormat('Y-m-d', $request['date']);

            $request['month'] = $birthDate->month;
            $request['day'] = $birthDate->day;
        }

        if (isset($request['month_day_range'])) {
            $dateRanges = explode(' to ', $request['month_day_range']);
            $dateFilterStart = isset($dateRanges[0]) ? Carbon::createFromFormat('Y-m-d', $dateRanges[0]) : Carbon::now();
            $dateFilterEnd = isset($dateRanges[1]) ? Carbon::createFromFormat('Y-m-d', $dateRanges[1]) : $dateFilterStart;

            $request['month_day_range'] = [];
            $monthDayRange = CarbonPeriod::create($dateFilterStart, $dateFilterEnd);
            
            foreach ($monthDayRange as $monthDay) {
                $request['month_day_range'][] = [(int)$monthDay->format('n'), (int)$monthDay->format('j')];
            }
        }

        $searchFilter = [
            'keyword'               => $request['keyword']              ?? null,
            'month'                 => $request['month']                ?? null,
            'day'                   => $request['day']                  ?? null,
            'month_day_range'       => $request['month_day_range']      ?? null,
            'city'                  => $request['city']                 ?? 0,
            'state'                 => $request['state']                ?? 0,
            'zip_code'              => $request['zip_code']             ?? 0,
            'lat'                   => $request['lat']                  ?? 0,
            'lng'                   => $request['lng']                  ?? 0,
            'distance'              => $request['distance']             ?? 100,
            'influencer'            => $influencer                      ?? AccountType::NO_SELECTION,
            'event_checked_in'      => $request['event_checked_in']     ?? 0,
            'event_invite'          => $request['event_invite']         ?? 0,
            'gender'                => $request['gender']               ?? array(0),
            'drinking'              => $request['drinking']             ?? array(0),
            'ethnicity'             => $request['ethnicity']            ?? array(0),
            'zodiac_sign'           => $request['zodiac_sign']          ?? array(0),
            'body_type'             => $request['body_type']            ?? array(0),
            'relationship_status'   => $request['relationship_status']  ?? array(0),
            'smoking'               => $request['smoking']              ?? array(0),
            'children'              => $request['children']             ?? array(0),
            'income_level'          => $request['income_level']         ?? array(0),
            'education_level'       => $request['education_level']      ?? array(0),
            'age_from'              => $request['age_from']             ?? 0,
            'age_to'                => $request['age_to']               ?? 0,
            'height_from'           => $request['height_from']          ?? 0,
            'height_to'             => $request['height_to']            ?? 0,
            'user_flagged'          => $request['user_flagged']         ?? 0,
        ];

        return $searchFilter;

    }

    /**
     * @param mixed $requestArray
     * @param bool $withLimit
     * @param int $perPage
     * @param int $limit
     * @param mixed $searchMethod=null
     *
     * @return mixed $query
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function elasticSearchBrowse($requestArray, $withLimit = false, int $perPage = 12, int $limit = 10, $searchMethod=null)
    {
        $searchText = isset($requestArray['keyword']) ? $requestArray['keyword'] : '*';

        $query = User::search($searchText, function ($meilisearch, $searchText, $options) use (
            $requestArray,
            $searchMethod
        ) {
            $filterQuery = SearchRepository::filter($requestArray, [], $searchMethod);

            if ($filterQuery!='') {
                $options['filter'] = $filterQuery;
            }

            if ($searchMethod==SearchMethod::NEWEST_MEMBERS) {
                $options['sort'] = array('created_at:desc');
            }

            if ($searchMethod==SearchMethod::RECENTLY_ACTIVE) {
                $options['sort'] = array('last_login_at:desc');
            }

            return $meilisearch->search($searchText, $options);

        })->query(function ($builder) {
            $builder->with('profile');
        });


        if ($withLimit) {
            return $query->take($limit)->get(); //default browse page
        }

        return $query->simplePaginate($perPage); //view more page
    }

    /**
     * @param mixed $requestArray
     * @param bool $withLimit
     * @param int $perPage
     * @param int $limit
     * @param mixed $searchMethod=null
     *
     * @return mixed $query
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function elasticSearchAdminUser($requestArray, $withLimit = false, int $perPage = 12, int $limit = 10, $searchMethod=null)
    {
        $searchText = isset($requestArray['keyword']) ? $requestArray['keyword'] : '*';

        $query = AdminUser::search($searchText, function ($meilisearch, $searchText, $options) use (
            $requestArray,
            $searchMethod
        ) {
            $filterQuery = SearchRepository::filter($requestArray, [], $searchMethod);

            if ($filterQuery!='') {
                $options['filter'] = $filterQuery;
            }

            if ($searchMethod==SearchMethod::NEWEST_MEMBERS) {
                $options['sort'] = array('created_at:desc');
            }

            if ($searchMethod==SearchMethod::RECENTLY_ACTIVE) {
                $options['sort'] = array('last_login_at:desc');
            }

            return $meilisearch->search($searchText, $options);

        })->query(function ($builder) {
            $builder->with('profile', 'adminRoles')->withoutGlobalScope(AccountNotSuspendedScope::class);
        });


        if ($withLimit) {
            return $query->take($limit)->get(); //default browse page
        }

        return $query->simplePaginate($perPage); //view more page
    }

    public static function elasticUserSearch(
        $requestArray,
        $withLimit = false,
        int $perPage = 12,
        int $limit = 10,
        $searchMethod=null,
        $friendIds = array(),
        $totalTakeLimit = 1000
    ) {
        $searchText = isset($requestArray['keyword']) ? $requestArray['keyword'] : '';

        // guard clauses, if friendids is empty, return empty query result
        if (empty($friendIds)) {
            if ($searchMethod==SearchMethod::EVENTS) {
                return User::whereIn('id', [])->get()->pluck('id')->toArray();
            }
            return User::whereIn('id', [])->simplePaginate($perPage);
        }

        $query = User::search($searchText, function ($meilisearch, $searchText, $options) use (
            $requestArray,
            $friendIds,
            $searchMethod
        ) {
            $filterQuery = SearchRepository::filter($requestArray, $friendIds, $searchMethod);

            if ($filterQuery!='') {
                $options['filter'] = $filterQuery;

            }

            return $meilisearch->search($searchText, $options);

        })->query(function ($builder) {
            $builder->with('profile');
        });


        if ($searchMethod==SearchMethod::EVENTS) {
            return $query->take($totalTakeLimit)->get()->pluck('id')->toArray();
        }

        if ($withLimit) {
            return $query->take($limit)->get(); //default browse page
        }

        return $query->simplePaginate($perPage); //view more page
    }

    //i will just park it here, we are no longer using the method, all searches will handle by meilisearch
    public static function laravelSearch($requestArray, $withLimit = false, int $perPage = 12, int $limit = 10, $searchMethod, $friendIds)
    {
        $query = User::searchSmoking($requestArray['smoking']);
        $searchMethodArray = array(SearchMethod::FRIENDS, SearchMethod::FAVORITES, SearchMethod::SENT_REQUESTS, SearchMethod::FRIENDS, SearchMethod::EVENTS);

        if (in_array($searchMethod, $searchMethodArray)) {
            $query = $query->whereIn('id', $friendIds);
        }

        $query->searchBirthday($requestArray['month'], $requestArray['day'])
                ->searchGender($requestArray['gender'])
                ->searchDrinking($requestArray['drinking'])
                ->searchHasChildren($requestArray['children'])
                ->searchIncomeLevel($requestArray['income_level'])
                ->searchEthnicity($requestArray['ethnicity'])
                ->searchZodiacSign($requestArray['zodiac_sign'])
                ->searchBodyType($requestArray['body_type'])
                ->searchRelationshipStatus($requestArray['relationship_status'])
                ->searchEducationLevel($requestArray['education_level'])
                ->searchInfluencer($requestArray['influencer'])
                ->searchHeight($requestArray['height_from'], $requestArray['height_to'])
                ->searchDistance($requestArray['lat'], $longitude = $requestArray['lng'], $requestArray['distance'])
                ->searchAge($requestArray['age_from'], $requestArray['age_to'])
                ->with(['primaryPhoto', 'media', 'interests'])
                ->ignoreLoginUser()
                ->onlyPublished();

        if ($searchMethod==SearchMethod::NEWEST_MEMBERS) {
            $query = $query->newestMember();
        }

        if ($searchMethod==SearchMethod::RECENTLY_ACTIVE) {
            $query = $query->recentlyActive();
        }

        if ($searchMethod==SearchMethod::ALL_MEMBERS) {
            if ($withLimit) {
                return $query->inRandomOrder()->limit($limit)->get(); //default browse page
            }

            return $query->inRandomOrder();
        }

        return $query;
    }
}
