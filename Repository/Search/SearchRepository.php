<?php

namespace App\Repository\Search;

use App\Models\User;
use App\Models\Group;
use App\Enums\AccountType;
use App\Enums\UserStatus;
use Illuminate\Support\Arr;

class SearchRepository implements SearchInterface
{

    /**
     * @param mixed $searchArray
     * @param array $friendIds
     * @param null $searchMethod
     *
     * @return string
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function filter($searchArray, $friendIds = [], $searchMethod = null)
    {
        $filter = array();

        array_push($filter, self::searchGeo($searchMethod, $searchArray['lat'], $searchArray['lng'], $searchArray['distance'], '_geoRadius'));

        // array_push($filter, self::searchDateRange($searchMethod, $searchArray['date_range_wo_year'], 'date_range_wo_year', [
        //     'ignoreYear' => true
        // ]));

        // if (isset($searchArray['month_range']) && count($searchArray['month_range']) >= 1) {
        //     $from = $searchArray['month_range'][0];
        //     $to = $searchArray['month_range'][1] ?? $searchArray['month_range'][0];
        //     array_push(
        //         $filter, 
        //         self::searchFilterRange(
        //             $searchMethod, 
        //             "{$from}", 
        //             "{$to}", 
        //             'birth_month'
        //         )
        //     );
        // }

        // if (isset($searchArray['day_range']) && count($searchArray['day_range']) >= 1) {
        //     $from = $searchArray['day_range'][0];
        //     $to = $searchArray['day_range'][1] ?? $searchArray['day_range'][0];
        //     array_push(
        //         $filter, 
        //         self::searchFilterRange(
        //             $searchMethod, 
        //             "{$from}", 
        //             "{$to}", 
        //             'birth_day'
        //         )
        //     );
        // }

        if (isset($searchArray['month_day_range']) && count($searchArray['month_day_range']) >= 1) {
            $query = collect($searchArray['month_day_range'])->reduce(function ($query, $monthDay) {
                if ($query !== '') {
                    $query .= ' OR ';
                }

                return "{$query}(birth_month = {$monthDay[0]} AND birth_day = {$monthDay[1]})";
            }, '');
            array_push($filter, "({$query})");
        }

        array_push($filter, self::searchFilter($searchMethod, $searchArray['month'], 'birth_month'));

        array_push($filter, self::searchFilter($searchMethod, $searchArray['day'], 'birth_day'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['gender'], 'gender'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['smoking'], 'smoking'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['drinking'], 'drinking'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['children'], 'children'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['income_level'], 'income_level'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['ethnicity'], 'ethnicity'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['zodiac_sign'], 'zodiac_sign'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['relationship_status'], 'relationship_status'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['education_level'], 'education_level'));

        array_push($filter, self::searchInfluencer($searchMethod, $searchArray['influencer'], 'influencer'));

        array_push($filter, self::searchFilterArray($searchMethod, $searchArray['body_type'], 'body_type'));

        array_push($filter, self::searchFilterRange($searchMethod, $searchArray['height_from'], $searchArray['height_to'], 'height'));

        array_push($filter, self::searchFilterRange($searchMethod, $searchArray['age_from'], $searchArray['age_to'], 'age'));


        if (!empty($friendIds)) {
            array_push($filter, self::arrayToFilter($searchMethod, $friendIds, 'id'));
        }

        $list = implode(' AND ', array_filter($filter));

        return $list;
    }

    /**
     * Convert range in Meilisearch filter range
     * @param mixed $searchMethod
     * @param mixed $from
     * @param mixed $to
     * @param mixed $fieldName
     *
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function searchFilterRange($searchMethod, $from, $to, $fieldName)
    {
        if ($fieldName=='height' && $from==55 && $to==89) {
            return null;
        }

        if ($fieldName=='age' && $from==18 && $to==99) {
            return null;
        }

        if ($from !== 0) {
            return  "( $fieldName >= $from AND $fieldName <= $to )";
        }

        return null;
    }

    /**
     * Elastic Geosearch
     * @param mixed $searchMethod
     * @param mixed $lat
     * @param mixed $lng
     * @param mixed $distance
     * @param mixed $fieldName
     *
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function searchGeo($searchMethod, $lat, $lng, $distance, $fieldName)
    {
        if ($lat!=0 and $lat!=null) {
            $distanceToMeters = $distance * 1609;

            return  "$fieldName ( $lat, $lng, $distanceToMeters)";
        }

        return null;
    }

    /**
     * Single Meilisearch filter
     * @param mixed $searchMethod
     * @param mixed $searchVar
     * @param mixed $fieldName
     *
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function searchFilterArray($searchMethod, $searchVar, $fieldName)
    {
        $searchVar = (array)$searchVar; //cast to array

        if (intval($searchVar[0])!=0 && $searchVar[0]!='') {
            if (count($searchVar)==1) {
                return $fieldName.'='.$searchVar[0];
            } else {
                return self::arrayToFilter($searchMethod, $searchVar, $fieldName);
            }

        }
    }

    /**
     * Meilisearch filter
     * @param mixed $searchMethod
     * @param mixed $searchVar
     * @param mixed $fieldName
     *
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function searchFilter($searchMethod, $searchVar, $fieldName)
    {
        if (intval($searchVar)!=0) {
            return $fieldName . "='" . $searchVar . "'";
        }
    }

    public static function searchDateRange($searchMethod, $searchArray, $fieldName, $options = [])
    {
        if (!empty($searchArray) && count($searchArray) === 2) {
            if (isset($options['ignoreYear']) && $options['ignoreYear'] === true) {
                return "{$fieldName} BETWEEN DATE_FORMAT({$searchArray[0]}, '%m-%d') AND DATE_FORMAT({$searchArray[1]}, '%m-%d')";
            }
            
            return "{$fieldName} BETWEEN {$searchArray[0]} AND {$searchArray[1]}";
        }
    }

    /**
     * Meilisearch filter
     * @param mixed $searchMethod
     * @param mixed $searchVar
     * @param mixed $fieldName
     *
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function searchInfluencer($searchMethod, $searchVar, $fieldName)
    {
        if ($searchVar!==0) {
            if ($searchVar==AccountType::PREMIUM) {
                return "$fieldName = $searchVar";
            }
        }

        return null;
    }

    /**
     * convert array to Meilisearch filter
     * @param mixed $searchMethod
     * @param mixed $filterArray
     * @param mixed $field
     *
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function arrayToFilter($searchMethod, $filterArray, $field)
    {
        $filter ='';
        $lastElement = end($filterArray);
        foreach ($filterArray as $key) {
            $filter .=  $field . '=' . $key;
            $lastElement!=$key ? $filter.=' OR ' : $filter;
        }

        return '(' . $filter . ')';
    }

    /**
    * @param mixed $request
    * @param int $userId
    *
    * @return [type]
    */
    public static function searchGroup(
        array $request = [],
        int $userId
    ) {
        // $filter = GroupRepository::filterGroup($request);
        $search_query = self::searchUserBioQuery($request, $userId);
        $data = Group::selectRaw("groups.*")
                        ->with([
                            'userGroups.user',
                            'userGroups.userProfile'
                        ])
                        ->withCount('userGroups as people_interested')
                        -> when($search_query, function ($query) use ($search_query) {
                            if (! empty($search_query)) {
                                return $query -> whereRaw($search_query);
                            }
                        })
                        ->orderBy("groups.name", 'asc')
                        ->distinct('events.id')
                        ->get();

        return $data;
    }

    /**
     * @param mixed $units : Will till what uni will used either in kilometer or Miles
     * Defautl : Miles
     */
    public static function switchUnit($units)
    {
        switch ($units) {
            default:
            case 'miles':
                //radius of the great circle in miles
                $gr_circle_radius = 3959;
            break;
            case 'kilometers':
                //radius of the great circle in kilometers
                $gr_circle_radius = 6371;
            break;
        }

        return $gr_circle_radius;
    }

    /**
     * @param string|null $keywords
     * @param array $options
     *
     * @return @var mixed $query
     */
    public function getUsers(string $keywords = null, array $options = [])
    {
        $excludeUsers = Arr::get($options, 'exclude_users', []);

        $query = User::query()
            ->where('status', UserStatus::PUBLISHED)
            ->orderBy('first_name')
            ->searchText(Arr::wrap($keywords));

        if (!empty($excludeUsers)) {
            $query->whereNotIn('id', $excludeUsers);
        }

        return $query->limit(10)->get();
    }

    public function getFriendsOf(User $user, $filters, $excludeUsers = [], $paginate=null)
    {
        $friendsQueryBuilder = User::where(function ($query) use ($user) {
            $query->whereIn('id', $user->friendsAdded()->select('users.id'))
                ->orWhereIn('id', $user->friendsAccepted()->select('users.id'));
        });

        //exclude friends that already invited
        if (!empty($excludeUsers)){

            $friendsQueryBuilder->whereNotIn('id', $excludeUsers);

        }

        if (isset($filters->keyword)) {
            $friendsQueryBuilder->where(function ($query) use ($filters) {
                $query->where('first_name', 'LIKE', '%'.$filters->keyword.'%');
                $query->orWhere('last_name', 'LIKE', '%'.$filters->keyword.'%');
            });
        }
        if ($paginate) {
            return $friendsQueryBuilder->paginate(10);
        }

        return $friendsQueryBuilder->get();

    }
}
