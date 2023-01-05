<?php

namespace App\Repository\Connection;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConnectionRepository implements ConnectionInterface
{

    public function getBirthdayCelebrants($request)
    {

        $dateFilter = $this->dateFilter($request['date'] ?? NULL);

        $birthdayCelebrants = User::where(DB::raw('DATE_FORMAT(birth_date, "%m-%d")'), $dateFilter->format('m-d'))
            ->onlyPublished()
            ->simplePaginate(12);

        return $birthdayCelebrants;
    }


    public function getEventConnection($request)
    {
        $dateRanges = isset($request['date_range']) ? $request['date_range'] : [];
        $dateFilterStart = $this->dateFilter($dateRanges[0] ?? null);
        $dateFilterEnd = $this->dateFilter($dateRanges[1] ?? $dateRanges[0] ?? null);

        $events = Event::where('event_start', '>=', $dateFilterStart->format('Y-m-d'))
            ->where('event_end', '<=', $dateFilterEnd->format('Y-m-d'))
            ->published()
            ->when(isset($request['city']), function($q) use ($request){
                return $q->where('city', $request['city']);
            })
            ->when(isset($request['state']), function ($q) use ($request) {
                return $q->where('state', $request['state']);
            })
            ->simplePaginate(12);

        return $events;

    }

    public function getGroupConnection($request, $birthdate)
    {

        $dateFilter = $this->dateFilter($birthdate ?? NULL);

        $groups = Group::with('user')
            ->where(DB::raw('DATE_FORMAT(created_at, "%m-%d")'), $dateFilter->format('m-d'))
            ->isPf()
            ->orWhere('birthday', $dateFilter->format('m-d'))
            ->published()
            ->simplePaginate(12);

        return $groups;

    }

    public function dateFilter($date = NULL): Carbon
    {
        return !empty($date) ? Carbon::createFromFormat('Y-m-d', $date) : Carbon::now();
    }

}
