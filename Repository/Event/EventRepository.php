<?php

namespace App\Repository\Event;


use App\Enums\RSVPType;
use App\Enums\TimeZone;
use App\Enums\RSVPStatus;
use App\Enums\EventUserFlagged;
use App\Enums\GatheringType;
use App\Events\UnpublishCancelled;
use App\Events\UnpublishPostponed;
use App\Events\UnpublishRescheduled;
use App\Jobs\SendGateKeeperInvitationEmail;
use App\Mail\EventInvitation;
use App\Mail\InviteGateKeeper;
use App\Mail\ThankYouEventAttendees;
use App\Models\Category;
use App\Models\EmailInvite;
use App\Models\Event;
use App\Models\EventTag;
use App\Models\RevokedQRCode;
use App\Models\RoleUser;
use App\Models\Tag;
use App\Models\Type;
use App\Models\User;
use App\Models\UserEvent;
use App\Models\UserGroup;
use App\Models\UserProfile;
use App\Notifications\EventInvitationNotification;
use App\Notifications\HuddleInvitationNotification;
use App\Notifications\Events\RemoveAsVIPNotification;
use App\Notifications\Events\SetAsVIPNotification;
use App\Notifications\PastEventInvitationNotification;
use App\Notifications\RSVPEvent;
use App\Notifications\RSVPLimitedEventOwner;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Media\MediaRepository;
use App\Repository\QrCode\QrCodeRepository;
use App\Repository\Role\RoleRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Enums\UserStatus;

class EventRepository implements EventInterface
{

    /**
     * Display pre loaded dropdown from table and enums
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getTypeAndCategory()
    {
        return [
            'type' => Type::all(),
            'category' => Category::all(),
            'timezone' => TimeZone::map(),
        ];
    }

    public static function getTimeZone()
    {
        return [
            'timezone' => TimeZone::map(),
        ];
    }


    /**
     * Retreive events
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getEvents(int $perPage)
    {
        return Event::withCount('userEvents as people_interested')
            ->with([
                'userEvents.user',
                'userEvents',
                'eventTags'
            ])
            ->paginate($perPage);
    }


    /**
     * This is being used for now but this method will retrieve all events on specific person
     * @param int $userId
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getEventsByUserId(int $userId)
    {
        return Event::withCount('userEvents as people_interested')
            ->with([
                'userEvents.user',
                'userEvents',
                'eventTags.tag'
            ])
            ->where('user_id', $userId)
            ->where('is_published', 1)
            ->where('event_end', '>=', date('Y-m-d'))
            ->paginate(15);
    }

    /**
     * This will be the logic for saving new events
     * @param array $data
     * @param int $userId
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function postEvent(array $data = [], int $userId)
    {

        $result = DB::transaction(function () use ($data, $userId) {

            $returnData = Event::eventsForm($data, $userId);

            $event = Event::create($returnData);

            $event->interests()->sync(Arr::get($data, 'interest'));

            self::postUserEvent(array('event_id' => $event->id), $userId);

            //add roles
            if (isset($data['roles'])) {
                RoleRepository::assignUserRole($data['roles'], $event, $userId);
            }

            if (isset($data['tags'])) {
                self::saveEventTag($event->id, $data['tags']);
            }

            return $event;

        });

        return $result;

    }

    /**
     * This will be the logic for updating events
     * @param array $data
     * @param int $userId
     * @param int $id
     * @return Array|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateEvent(array $data = [], int $userId, int $id)
    {
        $event = Event::whereId($id)
            ->withCount('userEvents as people_interested')
            ->with('userEvents.user', 'attendees', 'eventTags');

        $returnData = Event::eventsForm($data, $userId);
        unset($returnData['slug']); // Prevent updating the slug
        unset($returnData['user_id']); // Prevent updating the user id

        if (isset($data['tags'])) {
            self::saveEventTag($id, $data['tags']);
        }
        $event->first()->update($returnData);

        $event->first()->interests()->sync(Arr::get($data, 'interest'));

        $eventInstance = $event->first();

        //add roles
        RoleRepository::assignUserRole($data['roles'], $eventInstance, $userId);

        Log::debug('EventRepository::updateEvent by ' . $userId . ' event: ' . $id);

        return $eventInstance;
    }

    /**
     * This will be the logic for removing events
     * @param int $id
     * @return [type]
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function deleteEvent(int $id)
    {
        $result = DB::transaction(function () use ($id) {

            $event = Event::find($id);

            if (!$event->exists()) {
                return false;
            }

            $event->image ?? MediaRepository::unlinkMedia($event->image);

            $holdEvent = $event;

            $event->delete();

            return $holdEvent;
        });

        return $result;
    }

    /**
     * Display specific event by slug
     * @param string $slug
     * @param string|null $state
     * @param string|null $city
     * @return Array|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getEventBySlug(string $slug, string $state = null, string $city = null)
    {
        $event = Event::withCount('userEvents as people_interested')
            ->with(['userEvents', 'userEvents.user', 'attendees', 'eventTags'])
            ->where('slug', $slug);

        if (!$event->exists()) {
            return false;
        }

        return $event->first();
    }

    /**
     * Update published using slug
     * @param string $slug
     * @return Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function publishEventSlug(string $slug, int $userId)
    {
        $event = Event::withCount('userEvents as people_interested')
            ->with(['userEvents', 'userEvents.user', 'attendees', 'eventTags', 'eventUser'])
            ->where('slug', $slug);

        if (!$event->exists()) {
            return false;
        }

        if ($event->first()->is_published == 0) {

            $eventInstance = $event->first();
            $eventInstance->is_published = 1;
            $eventInstance->status = 2; // Admin Panel status Published
            $eventInstance->published_date = NOW();
            $eventInstance->save();

        }

        self::postUserEvent(array('event_id' => $event->first()->id),  $userId);

        return true;
    }

    /**
     * @param Event $event
     *
     * @return null
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function sendEmailNotificationToAttendeesOf(Event $event)
    {

        $attendees =   UserEvent::with('user')
            ->where('event_id', $event->id)
            ->where('user_id', "!=", $event->user_id) //dont send to owner
            ->get()
            ->unique('user_id');

        foreach ($attendees as $attendee) {
            // Check if valid to receive an email
            if ($attendee->user->validTypeAccount) {
                $attendee->user->notify(new EventInvitationNotification($attendee, $event)); //send mail notification
                Log::debug('EventRepository::sendEmailNotificationToAttendeesOf ' . $event->id . ' attendee: ' . $attendee->user_id);
            }
        }
    }

    /**
     * Get events near by location either from event|user|random
     * @param string $slug
     * @param string|null $state
     * @param string|null $city
     * @param int $eventId
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getNearByEvent(string $state = null, string $city = null, int $eventId)
    {

        $whereRaw = '(
                        ( state = "' . $state . '" ) AND
                        ( city = "' . $city . '" ) AND
                        ( event_end >= ' . date('Y-m-d') . ' ) AND
                        ( id != ' . $eventId . ' ) AND
                        ( is_published = 1 )
                    )';

        return Event::withCount('userEvents as people_interested')
            ->with(['userEvents', 'userEvents.user', 'attendees', 'eventTags'])
            ->when($whereRaw, function ($q) use ($whereRaw) {
                $q->whereRaw($whereRaw);
            })
            ->orderBy(DB::raw('ABS(DATEDIFF(event_start, NOW()))'))
            ->limit(6)
            ->get();
    }

    /**
     * This will be the logic for saving user event
     * @param array $data
     * @param int $userId
     * @return Array|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function postUserEvent(array $data = [], int $userId)
    {

        return UserEvent::firstOrCreate([
            'event_id' => $data['event_id'],
            'user_id'  => $userId,],[
            'event_id' => $data['event_id'],
            'user_id'  => $userId]
        );

    }

    /**
     * This will be the login for updaing user event
     * @param array $data
     * @param int $userId
     * @param int $id
     * @return HTTP_RESPONSE_CODE
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateUserEvent(array $data = [], int $userId, int $id)
    {
        $eventUser = UserEvent::whereId($id)
            ->where('user_id', $userId);

        if (!$eventUser->exists()) {
            return false;
        }

        // Check if new data not exist
        $isEventUser = UserEvent::where('event_id', $data['event_id'])
            ->where('user_id', $userId)
            ->exists();

        if ($isEventUser) {
            return true;
        }

        $returnData = UserEvent::userEventColumn($data, $userId);

        $eventUser->update($returnData);

        return true;
    }

    /**
     * This will be the logic for removing user event
     * @param int $id
     * @return Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function deleteUserEvent(int $id, int $userId)
    {
        $eventUser = UserEvent::where('event_id', $id)
            ->where('user_id', $userId);

        if (!$eventUser->exists()) {
            return false;
        }

        $holdEventUser = $eventUser->first();

        $eventUser->delete();

        return $holdEventUser;
    }

    /**
     * This will be the logic for saving event tag
     * @param int $eventId
     * @param array $tags
     * @return Collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function saveEventTag(int $eventId, array $tags): Collection
    {
        /** @var Event $event */
        $event = Event::query()->find($eventId);

        if (!empty($tags)) {
            $eventTags = collect($tags)
                ->map(function ($tagValue) {
                    if (is_numeric($tagValue)) {
                        // tag was pass as an ID
                        return Tag::query()->find($tagValue);
                    } elseif ($tagValue) {
                        // get tag if exists otherwise create
                        return Tag::query()->firstOrCreate([
                            'label' => $tagValue,
                        ]);
                    }

                    return null;
                })->filter();
        }

        if (isset($eventTags) && $eventTags->isNotEmpty()) {
            $event->eventTags()->sync($eventTags->pluck('id')->toArray());
        }

        return $eventTags ?? collect();
    }

    /**
     * Retrieve feature events by high priority
     * @return Object
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getFeatureEvent()
    {
        return Event::withCount('userEvents as people_interested')
            ->with(['userEvents', 'userEvents.user', 'attendees', 'eventTags'])
            ->onlyUpcoming()
            ->where('is_feature', 1)
            ->where('is_published', 1)
            ->orderBy('priority', 'ASC')
            ->first();
    }

    /**
     * Retrive all user that has interest on the event by slug
     * We included also the filtering in this section which we can filter the user
     * by there profile keyword can search [first_name, last_name, venue_location]
     * @param string $slug
     * @return Array|LengthAwarePaginator
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getAttendees(string $slug, array $request = [], int $perPage = 20, ?int $eventRSVPStatus = 0)
    {
        $userId = 0;

        if(authUser()) {
            $userId = authUser()->id;
        }

        $event = Event::where('slug', $slug)->first();

        //load search filter
        $searchFilter = BrowseRepository::searchFilter($request);

        $userEvent = UserEvent::where('event_id', $event->id)->select('user_id')
            ->whereNotIn('user_id', $event->eventAdmins->pluck('user_id')) // don't include admin who rsvp
            ->orderBy('created_at');

        $userEvent = $this->checkInFilter($searchFilter, $userEvent, $event);

        //filter by rsvp status
        $userEvent  = $this->filterByRSVPStatus($eventRSVPStatus, $userEvent);

        // Roles
        if (
            (isset($request['event_invite']) && $request['event_invite'] == 1) ||
            (isset($request['user_flagged']) && $request['user_flagged'] == 1)
        ) {
            $adminIds = [];
        } else {
            $adminIds = $event->roleUser->pluck('user.id')->toArray();
        }

        // Check wait listed
        if ( $eventRSVPStatus == RSVPStatus::WAIT_LISTED ) {
            $adminIds = [];
        }

        //cast to array of id
        $arrayIds = $this->generateArrayOfIdsForSearch($userEvent, $userId);

        $mergedIds = array_unique(array_merge($arrayIds, $adminIds));

        //elastic search
        $mergedIdsFromElasticSearch = BrowseRepository::elasticUserSearch($searchFilter, true, 20, 20, 'events', $mergedIds);

        //query on user model
        // $query = $this->getAttendeesInUserModel($mergedIdsFromElasticSearch, $mergedIds, $event->id);

        $sortIds = [
            ...$arrayIds,
            ...$adminIds,
        ];
        $sortIds[] = $event->user_id;
        if (authCheck()) {
            $authId = authUser()->id;
            $sortIds = array_filter($sortIds, function ($id) use ($authId) {
                return $id !== $authId;
            });
            $sortIds[] = authUser()->id;
        }

        $query = User::with([
            'profile',
            'attendingEvents',
            'userEvent' => function($query) use ($event) {
                $query->where('event_id', $event->id);
            }
        ])
        ->whereIn('id', $mergedIdsFromElasticSearch)
        ->orderByRaw('FIELD(id, '.trim(str_repeat('?,', count($sortIds)), ',').') DESC', $sortIds);

        return $query->paginate($perPage);
    }

    /**
     * @param mixed $arrayIdsFromElasticSearch
     * @param mixed $arrayIds
     * @param mixed $eventId
     *
     * @var mixed $query
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function getAttendeesInUserModel(array $arrayIdsFromElasticSearch, array $arrayIds, int $eventId)
    {
        $ids = implode(',', $arrayIds);

        //bring back to laravel eloquent since scout builder clauses only support basic numeric equality
        $query = User::withEventStatus($eventId)
            ->with(['profile', 'attendingEvents'])
            ->whereIn('users.id', $arrayIdsFromElasticSearch);

        if ($ids!="") {
            $query = $query->orderByRaw(DB::raw("FIELD(users.id, " . $ids . ")"));
        }

        return $query;
    }

    /**
     * @param Builder $userEvent
     * @param int $userId
     *
     * @var mixed $arrayIds
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function generateArrayOfIdsForSearch(Builder $userEvent, ?int $userId)
    {
        $arrayIds = $userEvent->orderBy('is_gatekeeper', 'desc')
            ->orderBy('id', 'asc')
            ->get()->pluck('user_id')->toArray();

        if($userId > 0) {
            if (in_array($userId, $arrayIds)) {
                if (($key = array_search($userId, $arrayIds)) !== false) {
                    unset($arrayIds[$key]);
                }

                array_unshift($arrayIds, $userId);
            }
        }

        return $arrayIds;
    }

    /**
     * @param mixed $eventRSVPStatus
     * @param mixed $limitedCapacityStatus
     * @param mixed $userEvent
     *
     * @param mixed $userEvent
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function filterByRSVPStatus(?int $eventRSVPStatus, Builder $userEvent)
    {
        if ($eventRSVPStatus == RSVPStatus::ATTENDING) {
            $userEvent->whereNotNull('qr_code');
        }

        if ($eventRSVPStatus == RSVPStatus::WAIT_LISTED) {
            $userEvent->whereNull('qr_code');
        }

        return $userEvent;
    }

    /**
     * @param mixed $searchFilter
     * @param mixed $userEvent
     * @param mixed $event
     *
     * @param mixed $userEvent
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function checkInFilter(array $filterEventInvite, Builder $userEvent, Event $event)
    {
        if ($filterEventInvite['event_checked_in'] === "1") {
            $userEvent->whereNull('attended_at');
        } elseif ($filterEventInvite['event_checked_in'] === "2") {
            $userEvent->whereNotNull('attended_at');
        }

        if ((int)$filterEventInvite['event_invite'] === 1) {

            $userEvent->where(function ($query) use ($event) {
                // $query->where('is_vip', false)
                //     ->where('user_id', '!=', $event->user_id);
                $query->whereNull('qr_code')
                    ->where('owner_flagged', 0);
            })->where('user_id', '!=', $event->user_id);

        } elseif ((int)$filterEventInvite['event_invite'] === 2) {

            $userEvent->where(function ($query) use ($event) {
                $query->whereNotNull('qr_code')
                    ->orWhere('user_id', $event->user_id);
            })->where('owner_flagged', 0);
        }

        if ((int)$filterEventInvite['user_flagged'] === 1) {
            $userEvent->where('owner_flagged', 1);

        }

        return $userEvent;
    }

    /**
     * This will return other components base on current event location
     * if no events near it will return the events base on user location
     * if still no event base on user location will generate random 6 events.
     *
     * @param int $eventId
     * @param string|null $city
     * @param string|null $state
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getOtherEvents(int $eventId)
    {
        $event = Event::withCount('userEvents as people_interested')
            ->with(['userEvents', 'userEvents.user', 'attendees', 'eventTags'])
            ->where('id', $eventId)
            ->where('is_published', 1)
            ->first();


        if (!$event) {
            return collect();
        }

        $userId = $event->user_id;
        if (authCheck() && authUser()->status === UserStatus::PUBLISHED) {
            $userId = authUser()->id;
        }

        $state          =   $event->state;
        $city           =   $event->city;
        $lat            =   $event->latitude;
        $long           =   $event->longitude;
        $distance       =   100;

        $eventBaseOnEventLoc = self::getUserEventsOrRandomEvent($state, $city, $lat, $long, $distance, $event, false);

        if (count($eventBaseOnEventLoc) == 0) {
            $user = UserProfile::where('user_id', $userId)->first();
            $eventBaseOnEventLoc = self::getUserEventsOrRandomEvent($user->state, $user->city, $user->lat, $user->long, $distance, $event, false);
        }
        if (count($eventBaseOnEventLoc) == 0) {
            $eventBaseOnEventLoc = self::getUserEventsOrRandomEvent($state, $city, $lat, $long, $distance, $event, true);
        }

        return collect($eventBaseOnEventLoc);
    }

    /**
     * Get all events that was create by login user
     * This method also will get all the events that
     * was interested by login user.
     * This will be determine wether getting all events by login user or by interested by user
     * depending on TAB parameter either 'interested' or 'my-events'
     *
     * @param int $userId
     * @param array $request
     *
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getMyEvents(int $userId, array $request = [], bool $myEvent = true)
    {
        $type = $request['type'] ?? 'my-events';

        $searchFilter = [
            'keyword'           => isset($request['keyword'])         ? $request['keyword']           : null,
            'city'              => isset($request['city'])            ? $request['city']              : '',
            'state'             => isset($request['state'])           ? $request['state']             : '',
            'zip_code'          => isset($request['zip_code'])        ? $request['zip_code']          : '',
            'lat'               => isset($request['lat'])             ? $request['lat']               : '',
            'lng'               => isset($request['lng'])             ? $request['lng']               : '',
            'distance'          => isset($request['distance'])        ? $request['distance']          : 100,
            'eventType'         => isset($request['type'])            ? $request['type']              : null,
            'category'          => isset($request['category'])        ? $request['category']          : null,
            'startDate'         => isset($request['start_date'])      ? $request['start_date']        : null,
            'endDate'           => isset($request['end_date'])        ? $request['end_date']          : null,
            'startTime'         => isset($request['start_time'])      ? $request['start_time']        : null,
            'endTime'           => isset($request['end_time'])        ? $request['end_time']          : null,
            'searchType'        => isset($request['search_type'])     ? $request['search_type']       : 'all',
            'featured'          => isset($request['featured'])        ? $request['featured']          : null
        ];

        $showUnpublished = $type === 'my-events';

        return self::search($searchFilter, false, true, $request['perPage'] ?? 15, 10, $myEvent, $type, $userId, $showUnpublished);
    }

    /**
     * Initialize all fields for our filter in events feature
     * @param mixed $request
     * @param int $userId
     * @param string $units
     * @return Array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function loadEvents(array $request = [], int $userId, string $type)
    {
        // $userProfile = UserProfile::where('user_id', $userId)->first();

        $searchFilter = [
            'keyword'           => isset($request['keyword'])         ? $request['keyword']           : null,
            'city'              => isset($request['city'])            ? $request['city']              : null,
            'state'             => isset($request['state'])           ? $request['state']             : null,
            'zip_code'          => isset($request['zip_code'])        ? $request['zip_code']          : '', //$userProfile->zip_code,
            'lat'               => isset($request['lat'])             ? $request['lat']               : '', //$userProfile->latitude,
            'lng'               => isset($request['lng'])             ? $request['lng']               : '', //$userProfile->longitude,
            'distance'          => isset($request['distance'])        ? $request['distance']          : null,
            'eventType'         => isset($request['type'])            ? $request['type']              : null,
            'category'          => isset($request['category'])        ? $request['category']          : null,
            'startDate'         => isset($request['start_date'])      ? $request['start_date']        : null,
            'endDate'           => isset($request['end_date'])        ? $request['end_date']          : null,
            'startTime'         => isset($request['start_time'])      ? $request['start_time']        : null,
            'endTime'           => isset($request['end_time'])        ? $request['end_time']          : null,
            'searchType'        => isset($request['search_type'])     ? $request['search_type']       : 'all',
            'featured'          => isset($request['featured'])        ? $request['featured']          : null,
            'gathering_type'    => isset($request['gathering_type'])  ? $request['gathering_type']    : null
        ];

        return $searchFilter;
    }

    /**
     * This will be the starting on our search functionality for events
     * all related scoped are place inside event model
     * @param mixed $requestArray
     * @param bool $withLimit
     * @param bool $withPagination
     * @param int $perPage
     * @param int $limit
     * @param bool $isMyEvent
     * @param string $tab
     * @param int|null $userId
     * @param bool $showUnpublished
     * @return Array
     * @throws \Exception
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function search(
        $requestArray,
        $withLimit = false,
        $withPagination = true,
        int $perPage = 12,
        int $limit = 10,
        $isMyEvent = false,
        string $tab = 'my-events',
        int $userId = null,
        bool $showUnpublished = false
    ) {

        try {

            $searchText = isset($requestArray['keyword']) ? explode(" ", $requestArray['keyword']) : '';

            $trimmedKeyword = trim($requestArray['keyword']);

            // Display first the exact title match
            $query = Event::searchText($searchText)
                ->orderByRaw("IF(title = '{$trimmedKeyword}', 1, 0) DESC");

            $query = $query->searchEventType($requestArray['eventType'])
                ->searchEventCategory($requestArray['category'])
                ->searchStartDate($requestArray['startDate'], $requestArray['endDate'])
                ->searchEndDate($requestArray['startDate'], $requestArray['endDate'])
                ->searchStartTime($requestArray['startTime'], $requestArray['endTime'])
                ->searchEndTime($requestArray['startTime'], $requestArray['endTime'])
                ->searchDisplayType($requestArray['searchType'])
                // ->searchCity($requestArray['city'])
                // ->searchState($requestArray['state'])
                // ->searchZipCode($requestArray['zip_code'])
                // ->searchDistance($requestArray['lat'], $longitude = $requestArray['lng'], $requestArray['distance'])
                ->when(isset($requestArray['featured']) && !is_null($requestArray['featured']), function (Builder $query) use ($requestArray) {
                    $query->where('is_feature', $requestArray['featured']);
                });

            if (isset($requestArray['gathering_type']) ) {
                switch ($requestArray['gathering_type']) {
                    case GatheringType::EVENT:
                        $query->eventsOnly();
                        break;

                    case GatheringType::HUDDLE:
                        $query->huddleOnly();
                        break;

                    default:
                        break;
                }
            }

            $query = $query->with(['primaryPhoto', 'userEvents', 'attendees', 'eventTags', 'emailInvites.event'])
                ->withCount('userEvents as people_interested');

            $rolesEvent = RoleUser::roleUserEvents($userId)->pluck('resource_id');

            if ($isMyEvent) {
                $query->when($tab == 'my-events', function ($q) use ($userId) {
                    return $q->where('user_id', $userId);
                });
                $query->when($tab == 'administrator-roles', function ($q) use ($rolesEvent) {
                    return $q->whereIn('id', $rolesEvent)->published();
                });
                $query->when($tab == 'wall', function ($q) use ($userId) {
                    return $q->whereHas('userEvents', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })->orWhere('user_id', $userId);
                });
                $query->when(!in_array($tab, array('my-events', 'administrator-roles', 'wall')), function ($q) use ($userId) {
                    $q->whereHas('userEvents', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })->where('user_id', '!=', $userId);
                });
            }


            if (!$showUnpublished) {
                $query->published();
            }

            if ($requestArray['searchType']=='past_events') {
                $query->onlyPast();
            }

            if ($withLimit) {
                return $query->limit($limit)
                        ->orderBy(DB::raw('ABS(DATEDIFF(event_start, NOW()))'))
                        ->paginate($perPage); //default browse page
            }
            if (!$withPagination) {
                return $query->get(); //default browse page
            }

            $query->orderBy(DB::raw('ABS(DATEDIFF(event_start, NOW()))'));

            return $query->paginate($perPage); //view more page
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private static function getUserEventsOrRandomEvent($state, $city, $lat, $long, $distance, $event, $feature)
    {
        $searchFilter = [
            'state'             => $state,
            'city'              => $city,
            'lat'               => $lat,
            'lng'               => $long,
            'distance'          => $distance,
            'eventType'         => $event->type,
            'category'          => $event->category,
            'eventId'           => $event->id
        ];

        $tags = EventTag::where('event_id', $event->id)
            ->select('tag_id')
            ->get()
            ->toArray();

        $whereRaw = "(
                        ( type      = '" . $searchFilter['eventType'] . "' ) OR
                        ( category  = '" . $searchFilter['category'] . "' )
                    )";

        $events = Event::with(['primaryPhoto', 'userEvents', 'attendees', 'eventTags'])
            ->withCount('userEvents as people_interested')
            ->where('id', '!=', $searchFilter['eventId'])
            ->where('event_end', '>=', date('Y-m-d'))
            ->orderBy(DB::raw('ABS(DATEDIFF(event_start, NOW()))'))
            ->where('is_published', '=', 1);

        if ($feature) {
            return $events->orderBy('is_feature', 'DESC')
                ->limit(6)
                ->get();
        } else {
            return $events->whereHas('eventTags', function ($q) use ($tags) {
                $q->whereIn('tags.id', $tags);
            })
                ->whereRaw($whereRaw)
                ->limit(6)
                ->get();
            // ->searchDistance($searchFilter['lat'], $longitude = $searchFilter['lng'], $searchFilter['distance'])
        }
    }

    public static function setVip(int $eventId, int $userId)
    {
        $userEvent = UserEvent::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('owner_flagged', EventUserFlagged::NOT_FLAGGED)
            ->firstOrFail();

        $userEvent->is_vip = 1;
        $userEvent->save();

        /**
         * Send a notification to user that he's been invited as VIP
         * @author Angelito Tan
         */
        if ($userEvent->is_vip) {
            Notification::send(
                $userEvent->user,
                new SetAsVIPNotification($userEvent->user, $userEvent->event)
            );
        }


        return $userEvent;
    }

    public static function unsetVip(int $eventId, int $userId, int $mediaId = null, string $reason, int $authUserId)
    {
        $userEvent = UserEvent::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->firstOrFail();

            RevokedQRCode::create([
                'reporter_id' => $authUserId, //this is on the assumption that the user is logged in
                'media_id' => $mediaId,
                'remarks' => $reason,
                'entity_id' => $userEvent->id,
                'entity' => UserEvent::class,
                'qr_code' => $userEvent->qr_code,
            ]);

        $userEvent->is_vip = 0;
        $userEvent->flagged_remark = $reason;
        if ($mediaId) $userEvent->media_id = $mediaId;
        $userEvent->save();

        /**
         * Send a notification to user that he's been remove from the VIP lists
         * @author Angelito Tan
         */
        Notification::send(
            $userEvent->user,
            new RemoveAsVIPNotification($userEvent->user, $userEvent->event)
        );

        return $userEvent;
    }

    public static function setGateKeeper(int $eventId, int $userId)
    {
        $isGatekeeper = 1;
        $data = 201;

        $userEvent = UserEvent::where('event_id', $eventId)
            ->where('user_id', $userId);

        if ($userEvent->exists() && $userEvent->first()->is_gatekeeper == 1) {
            $isGatekeeper = 0;
            $data = 200;
        }

        $userEvent->update([
            'is_gatekeeper' => $isGatekeeper
        ]);

        $to = User::whereId($userId)->first();
        $from = auth()->user();

        $event = Event::whereId($eventId)->first();
        $email = [
            'to' => [
                'email' => $to->email,
                'full_name' => $to->first_name . ' ' . $to->last_name
            ],
            'from' => [
                'email' => $from['email'],
                'full_name' => $from['first_name'] . ' ' . $from['last_name']
            ],
            'event' => [
                'title' => $event->title,
                'event_start' => date('l jS \of F Y', strtotime($event->event_start)) . ' ' . date('h:i:s A', strtotime($event->start_time)),
                'event_end' => date('l jS \of F Y', strtotime($event->event_end)) . ' ' . date('h:i:s A', strtotime($event->end_time))
            ],
            'data' => $data
        ];

        if ($to->validTypeAccount) {
            SendGateKeeperInvitationEmail::dispatch($email)->onQueue('high');
        }

        return $data;
    }

    /***
     * MAIL SETUP
     */

    /**
     * @param array $request
     * @param string $validation_token
     *
     * @return [type]
     */
    public static function mail(array $email)
    {
        $details = [
            'email_to_name' => $email['to']['full_name'],
            'email_from_name' => $email['from']['full_name'],
            'event_title' => $email['event']['title'],
            'event_start' => $email['event']['event_start'],
            'event_end' => $email['event']['event_end'],
            'status' => $email['data'] == 201 ? ' invited to be a ' : ' removed as a',
            'subject'  => 'Gatekeeper Invitation',
            'copy_year' => env('MAIL_COPY_YEAR') ? env('MAIL_COPY_YEAR') : date('Y'),
            'mail_from' => env('MAIL_FROM') ? env('MAIL_FROM') : 'support@perfectfriend.com',
        ];

        Mail::to($email['to']['email'])->send(new InviteGateKeeper($details));

        return true;
    }

    /**
     * @param User $user
     * @param Event $event
     *
     * @return void
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function setAsAttended(User $user, Event $event)
    {
        $userEvent = $event->userEvents()
            ->where('user_id', $user->id)
            ->first();
        $userEvent->attended_at = now();
        $userEvent->confirmed_by = authCheck() ? authUser()->id : null;
        $userEvent->save();
    }

    public static function updateLimitedCapacityCount($event)
    {
        if ($event->rsvp_type === RSVPType::LIMITED) {
            $attendeesCount = UserEvent::where('event_id', $event->id)
                ->whereNotNull('qr_code')
                ->where('user_id', '!=', $event->user_id)
                ->count();

            $queueCount = UserEvent::where('event_id', $event->id)
                ->whereNull('qr_code')
                ->count();

            if ($attendeesCount < $event->max_capacity && $queueCount > 0) {
                $difference = $event->max_capacity - $attendeesCount;

                $usersOnQueue = UserEvent::where('event_id', $event->id)
                    ->whereNull('qr_code')
                    ->orderBy('created_at')
                    ->limit($difference)
                    ->get();

                foreach ($usersOnQueue as $user) {
                    $eventHost = User::find($event->user_id);
                    //notify host
                    if ($user->user->validTypeAccount){
                        $user->user->notify(new RSVPLimitedEventOwner($eventHost, $event));
                    }

                    //create qr code
                    QrCodeRepository::generateQrCodeOn($user);

                    //notify user
                    if ($user->user->validTypeAccount){
                        $user->user->notify(new RSVPEvent($user));
                    }
                }
            }
        }
    }

    /**
     * @param Event $event
     * @param array $request
     * @param int $userId
     *
     * @return [type]
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function unpublish(Event $event, array $request, int $userId)
    {
        switch ($request['status']) {
            case 'postponed':
                $event->update([ 'is_published' => 0]);
                event(new UnpublishPostponed($event));
                break;

            case 'rescheduled':

                $new_event_start    = Carbon::createFromFormat('Y-m-d', $request['new_event_start']);
                $new_start_time     = date("H:i:s", strtotime($request['new_start_time']));
                $new_event_end      = Carbon::createFromFormat('Y-m-d', $request['new_event_end']);
                $new_end_time       = date('H:i:s', strtotime($request['new_end_time']));
                $new_time_zone       = $request['new_time_zone'];

                $event->update([
                    'is_published'      => 1,
                    'event_start'   => $new_event_start,
                    'start_time'    => $new_start_time,
                    'event_end'     => $new_event_end,
                    'end_time'      => $new_end_time,
                    'timezone_id'      => $new_time_zone,
                ]);

                event(new UnpublishRescheduled($event));
                break;

            case 'cancelled':

                $event->update([
                    'is_published' => 0,
                    'cancelled_at' => Carbon::now(),
                ]);
                event(new UnpublishCancelled($event));
                break;

            default:
                $event->update([ 'is_published' => 0 ]);
            }

        return $event;
    }

    /**
     * @param Event $event
     *
     * @return Event
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function sendThanksToAttendeesOf(Event $event): Event
    {
        if ($event->sent_thanks_at) {
            throw new \Exception('Already sent a thank you.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $attendeesWhoCheckedIn = $event->attendeesWhoCheckedIn;
        foreach ($attendeesWhoCheckedIn as $attendeeWhoCheckedIn) {

            // Check if is-case is valid to receive an email
            if ($attendeeWhoCheckedIn->user->validTypeAccount) {
                Mail::to($attendeeWhoCheckedIn)
                ->queue(new ThankYouEventAttendees($event));
            }
        }
        $event->sent_thanks_at = Carbon::now();
        $event->save();

        return $event;
    }

    /**
     * Set member as flagged
     * @author Angelito Tan
     */
    public static function setFlagged(String $type, int $eventId, int $userId, int $media_id = null, String $reason = null)
    {
        $userEvent = UserEvent::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // $userEvent->is_vip = 0;
        if ($media_id) $userEvent->media_id = $media_id;
        $userEvent->flagged_remark = $reason;

        if($type == 'uninvite') {
            //create an entry on the revoked qr codes
            if ($userEvent->qr_code) {

                $revokedCode = RevokedQRCode::create([
                    'reporter_id' => auth()->id(), //this is on the assumption that the user is logged in
                    'media_id' => $media_id,
                    'remarks' => $reason,
                    'entity_id' => $userEvent->id,
                    'entity' => UserEvent::class,
                    'qr_code' => $userEvent->qr_code,
                ]);

            }

            if(!$revokedCode) {
                //for logging purposes
                Log::debug('Unable to record the revoked Event qr code' . $userEvent->qr_code);
            }

            // $userEvent->qr_code = null;
            $userEvent->owner_flagged = 0;
            Log::info(0);
        } else {
            $userEvent->owner_flagged = 1;
            Log::info(1);
        }


        $userEvent->save();

        return $userEvent;
    }

    /**
     * Set member as flagged
     * @author Angelito Tan
     */
    public static function unsetFlagged(String $type, int $eventId, int $userId)
    {
        $userEvent = UserEvent::where('event_id', $eventId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $userEvent->owner_flagged = 0;
        // $userEvent->media_id = null;
        // $userEvent->flagged_remark = null;

        // QrCodeRepository::generateQrCodeOn($userEvent);

        $userEvent->save();

        return $userEvent;
    }

    /**
     * Create event name & location
     *
     * @param array $request
     * @param int $userId
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function createNameLocation(array $request, int $userId)
    {
        $uniqueCode = QrCodeRepository::generateUniqueCode((new Event));
        $title = ucfirst(Arr::get($request, 'title'));
        $slug =  Str::slug($title . '-' . $uniqueCode);
        $event = Event::create([
            'title'          => $title,
            'setting'        => Arr::get($request, 'setting'),
            'venue_location' => Arr::get($request, 'venue.venue_location'),
            'secondary_location' => Arr::get($request, 'secondary_location'),
            'street_address' => Arr::get($request, 'venue.street'),
            'city'           => Arr::get($request, 'venue.city'),
            'state'          => Arr::get($request, 'venue.state'),
            'zip_code'       => Arr::get($request, 'venue.zip_code'),
            'latitude'       => Arr::get($request, 'venue.latitude'),
            'longitude'      => Arr::get($request, 'venue.longitude'),
            'user_id'        => $userId,
            'slug'           => $slug,
            'rsvp_type'    =>  Arr::get($request, 'rsvp_type'),
            'max_capacity' =>  Arr::get($request, 'max_capacity'),
            'live_chat_enabled' => (int) Arr::get($request, 'chat_enabled', 0),
            'live_chat_type'    => Arr::get($request, 'chat_type', 0) ?? 0,
            'gathering_type'   => Arr::get($request, 'gathering_type', GatheringType::EVENT),
        ]);

        // Add owner to the user_event
        UserEvent::create([
            'event_id' => $event->id,
            'user_id' => $userId
        ]);

        return $event;
    }

    /**
     * Update event name & location
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function updateNameLocation(array $request, Event $event)
    {
        $event->update(
            [
                'title'          => Arr::get($request, 'title'),
                'setting'        => Arr::get($request, 'setting'),
                'venue_location' => Arr::get($request, 'venue.venue_location'),
                'secondary_location' => Arr::get($request, 'secondary_location'),
                'street_address' => Arr::get($request, 'venue.street'),
                'city'           => Arr::get($request, 'venue.city'),
                'state'          => Arr::get($request, 'venue.state'),
                'zip_code'       => Arr::get($request, 'venue.zip_code'),
                'latitude'       => Arr::get($request, 'venue.latitude'),
                'longitude'      => Arr::get($request, 'venue.longitude'),
                'rsvp_type'    =>  Arr::get($request, 'rsvp_type'),
                'max_capacity' =>  Arr::get($request, 'max_capacity'),
                'live_chat_enabled' => (int) Arr::get($request, 'chat_enabled', 0),
                'live_chat_type'    => Arr::get($request, 'chat_type', 0) ?? 0
            ]
        );
        return $event;
    }

    /**
     * Update the initial event data which is the type & category
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function updateTypeCategory(array $request, Event $event)
    {
        $event->update(
            [
                'type'          => Arr::get($request, 'type'),
                'category'      => Arr::get($request, 'category')
            ]
        );
        $event->interests()->sync(Arr::get($request, 'interest'));
        return $event;
    }

    /**
     * Update event data for description
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function updateDescription(array $request, Event $event) {
        $event->update(
            [
                'description' =>  Arr::get($request, 'description')
            ]
        );
        return $event;
    }

    /**
     * Update event data for media & rsvp, chat setting
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function updateMediaSetting(array $request, Event $event) {
        $event->update(
            [
                'image'        =>  Arr::get($request, 'image'),
                'video_id'     =>  Arr::get($request, 'video_id')
            ]
        );
        return $event;
    }

    /**
     * Update event data for roles and responsibilities
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function updateRolesResponsibilities(array $request, Event $event, int $userId) {
        RoleRepository::assignUserRole($request['roles'], $event, $userId);
        return $event;
    }

    /**
     * Update event data for date & time, timezone
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function updateDateTime(array $request, Event $event){
        $event->update(
            [
                'event_start' => Arr::get($request, 'event_start'),
                'start_time'  => Arr::get($request, 'start_time'),
                'event_end'   => Arr::get($request, 'event_end'),
                'end_time'    => Arr::get($request, 'end_time'),
                'timezone_id' => Arr::get($request, 'timezone_id')
            ]
        );
        return $event;
    }

    /**
     * This will get event data created by the owner
     *
     * @param int $eventId
     * @param int $userId
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function getEvent(int $eventId, int $userId) {
        return Event::where('id', $eventId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

}
