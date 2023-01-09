<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventSteps;
use App\Events\UserDiscussionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventDateTimeRequest;
use App\Http\Requests\EventDeleteRequest;
use App\Http\Requests\EventDescriptionRequest;
use App\Http\Requests\EventMediaRequest;
use App\Http\Requests\EventNameLocationRequest;
use App\Http\Requests\EventRequest;
use App\Http\Requests\EventRolesRequest;
use App\Http\Requests\EventUnpublishRequest;
use App\Http\Requests\EventUpdateRequest;
use App\Http\Requests\SetGateKeeperRequest;
use App\Http\Requests\SetVipRequest;
use App\Http\Requests\TypeCategoryInterestRequest;
use App\Http\Resources\EventAttendeeResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventStepsResource;
use App\Http\Resources\PastInviteResource;
use App\Http\Resources\UserSearchResource;
use App\Models\Event;
use App\Models\User;
use App\Repository\Event\EventRepository;
use App\Repository\Invitation\InvitationRepository;
use App\Repository\Role\RoleRepository;
use App\Repository\Search\SearchRepository;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use Exception;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class EventController extends Controller
{
    use ApiResponser;

    private $eventRepository;
    private $searchRepository;
    private $invitationRepository;

    public function __construct(EventRepository $eventRepository,
    SearchRepository $searchRepository, InvitationRepository $invitationRepository)
    {
        $this->eventRepository = $eventRepository;
        $this->searchRepository = $searchRepository;
        $this->invitationRepository = $invitationRepository;
    }

    /**
     * Retrieving all events
     * @return EventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request)
    {
        try {

            $keyword = $request->keyword;

            $data = $this->eventRepository->search(
                $this->eventRepository->loadEvents(
                    $request->all(),
                    authCheck() ? authUser()->id : 1,
                    $request->searchType ?? 'all'
                ),
                false,
                true,
                $request->perPage ?? 15,
                10,
                false,
                'my-events',
                authCheck() ? authUser()->id : 1
            );

            if ($data) {

                $result = EventResource::collection($data);

                return $this->successResponse($result, null, Response::HTTP_OK, true);
            }

            return $this->successResponse([]);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function searchPastInvites(Request $request)
    {

        try {
            $perPage = 10;
            $keyword = $request->keyword;
            $user = User::find(authUser()->id);
            $pastEvents = $user->pastEvents()
                ->select(
                    'events.id',
                    DB::raw("'event' AS past_type"),
                    'events.title',
                    'events.slug',
                )
                ->whereHas('userEvents', function ($query) {
                    $query->where('user_id', '!=', authUser()->id);
                })->where('title', 'like', "%" . $keyword . "%");

            $groups = $user->myGroups()
                ->select(
                    'groups.id',
                    DB::raw("'group' AS past_type"),
                    DB::raw("groups.name AS title"),
                    'groups.slug',
                )
                ->whereHas('members', function ($query) {
                    $query->where('user_id', '!=', authUser()->id);
                })->where('name', 'like', "%" . $keyword . "%");
            $result = $pastEvents->union($groups)
                ->paginate($perPage);

                return $this->successResponse(PastInviteResource::collection($result), null, Response::HTTP_OK, true);

        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Store new events
     * @param EventRequest $request
     * @return EventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function saveEvents(EventRequest $request)
    {
        $data = $this->eventRepository->postEvent($request->all(), authUser()->id);

        if ($data == false) {
            return $this->errorResponse('Event already exist', Response::HTTP_CONFLICT);
        }

        $data->load('eventTags');

        $result = new EventResource($data);

        return $this->successResponse($result, null, Response::HTTP_CREATED);
    }

    /**
     * Store new events
     * @param EventRequest $request
     * @return EventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(EventRequest $request)
    {

        $data = $this->eventRepository->postEvent($request->all(), authUser()->id);

        if ($data == false) {
            return $this->errorResponse('Event already exist', Response::HTTP_CONFLICT);
        }

        $data->load('eventTags');

        $result = new EventResource($data);

        return $this->successResponse($result, null, Response::HTTP_CREATED);
    }

    /**
     * Update specific events using id
     * @param EventRequest $request
     * @param mixed $id
     * @return EventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(EventUpdateRequest $request, $id)
    {

        $data = $this->eventRepository->updateEvent($request->all(), authUser()->id, $id);

        if ($data == false) {
            return $this->errorResponse('Record did not match in any records', Response::HTTP_BAD_REQUEST);
        }

        $result = new EventResource($data);

        return $this->successResponse($result);
    }

    /**
     * Delete Event using the parameter of id
     * @param mixed $id
     * @return EventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(EventDeleteRequest $request, Event $event)
    {
        $data = $this->eventRepository->deleteEvent($event->id);

        if ($data == false) {
            return $this->errorResponse('Record did not match in any records.', Response::HTTP_BAD_REQUEST);
        }

        $result = new EventResource($data);

        return $this->successResponse($result);
    }

    /**
     * Delete Event using the parameter of id
     * @param mixed $slug
     * @return EventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getEventBySlug($slug, Request $request)
    {
        $data = $this->eventRepository->getEventBySlug($slug, $request->state, $request->city);

        if ($data == false) {
            return $this->errorResponse('Record did not match in any records.', Response::HTTP_BAD_REQUEST);
        }

        return $this->successResponse(new EventResource($data));
    }

    /**
     * Retrieving the Type, Category and TimeZone
     * @return array ['type','category','timezone']
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getTypeAndCategory()
    {
        try {
            return $this->successResponse($this->eventRepository->getTypeAndCategory());
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Retrieving TimeZone
     * @return array [timezone']
     */
    public function getTimeZone()
    {
        try {
            return $this->successResponse($this->eventRepository->getTimeZone());
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Published event by slug
     * @param mixed $slug
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function publishedEventBySlug(Request $request, $slug)
    {
        $data = $this->eventRepository->publishEventSlug($slug, authUser()->id);

        if ($data == false) {
            return $this->errorResponse('Record did not match in any records.', Response::HTTP_BAD_REQUEST);
        }

        if (isset($request->postToWall) && (int)$request->postToWall === 1) {
            $event = $this->eventRepository->getEventBySlug($slug);
            event(new UserDiscussionEvent(authUser()->id, 'event_published', $event->makeHidden(['userEvents', 'userEvents.user', 'attendees', 'eventTags'])));
        }

        return $this->successResponse(new EventResource(Event::where('slug', $slug)->with(['attendees', 'eventTags'])->first()));
    }

    /**
     * Retrieve the feature events
     * @return EventResource
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getFeatureEvent()
    {
        try {
            $featured = $this->eventRepository->getFeatureEvent();

            if (!$featured) {
                return $this->successResponse(null);
            }

            return $this->successResponse(new EventResource($featured));
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }


    /**
     * Retrieving the Type, Category and TimeZone
     * @return array ['type','category','timezone']
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getAttendees(Request $request)
    {
        try {

            $data = $this->eventRepository->getAttendees($request->slug, $request->all(), $request->perPage ?? 20, $request->event_rsvp_status ?? 0);

            $result = EventAttendeeResource::collection($data);

            return $this->successResponse($result, null, Response::HTTP_OK, true);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Retreiving other events near current events or
     * base on user location or random events
     *
     * @param Request $request
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getOtherEvents(Request $request)
    {
        $data = $this->eventRepository->getOtherEvents($request->event_id);

        if ($data == false) {
            return $this->errorResponse('Record did not match in any records.', Response::HTTP_BAD_REQUEST);
        }

        $result = EventResource::collection($data);

        return $this->successResponse($result);
    }

    /**
     * Setting user as a VIP if request is true
     * @param SetVipRequest $request
     * @return [type]
     */
    public function setVip(SetVipRequest $request)
    {
        $event = Event::find($request->event_id);

        $userId = authUser()->id;
        if($userId != $event->user_id && !Gate::allows('can_invite_vip', $event)){

            return $this->errorResponse('Unauthorized', Response::HTTP_BAD_REQUEST);

        }

        try {
            $data = $this->eventRepository->setVip($request->event_id, $request->user_id);

            return $this->successResponse($data, 'VIP', Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Setting user as a VIP if request is true
     * @param SetVipRequest $request
     * @return [type]
     */
    public function unsetVip(SetVipRequest $request)
    {
        $event = Event::find($request->event_id);

        $authUserId = authUser()->id;
        if($authUserId != $event->user_id && !Gate::allows('can_invite_vip', $event)) {

            return $this->errorResponse('Unauthorized', Response::HTTP_BAD_REQUEST);
        }

        try {
            $data = $this->eventRepository->unsetVip($request->event_id, $request->user_id, $request->media_id, $request->reason, $authUserId);

            return $this->successResponse($data, 'Not VIP', Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Setting user as a gatekeeper if request is true
     * @param SetGateKeeperRequest $request
     * @return [type]
     * @@author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function setGateKeeper(SetGateKeeperRequest $request)
    {
        try {
            $data = $this->eventRepository->setGateKeeper($request->event_id, $request->user_id);

            if ($data == 201) {
                return $this->successResponse($data, 'Assigned', Response::HTTP_CREATED);
            }
            return $this->successResponse($data, 'Remove Assignee', Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Set event to feature since the admin panel is not already implemented
     * @param mixed $slug
     * @return mixed
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function setEventIsFeatureBySlug($slug)
    {
        try {
            $checkIfSlug = Event::where('slug', $slug);
            if ($checkIfSlug->exists()) {
                $checkIfSlug->update(['is_feature' => 1]);
                return $this->successResponse([], 'Success', Response::HTTP_OK);
            }
            return $this->errorResponse('Record did not match in any records.', Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function setAsAttended(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $event = Event::findOrFail($request->event_id);

        // Checks if not an event owner
        if ($event->user_id !== authUser()->id) {
            // Verify if gatekeeper of this event
            $abilities = new RoleRepository(authUser()->id, $event->id);

            if (!$abilities->canScanQr()) {

                return $this->errorResponse('You must be an owner or a gatekeeper of this event to do this action.', Response::HTTP_FORBIDDEN);

            }
        }

        $userEvent = $event->userEvents()
            ->where('user_id', $user->id)
            ->firstOrFail();
        // Checks if already attended
        if ($userEvent->attended_at) {
            $formattedDate = Carbon::parse($userEvent->attended_at)->format('m F Y, g:i A');
            throw new \Exception("This ticket is already used on $formattedDate", Response::HTTP_CONFLICT);
        }

        EventRepository::setAsAttended($user, $event);

        return $this->successResponse([], 'Successfully attended', Response::HTTP_OK);

    }

    public function unpublish(EventUnpublishRequest $request, Event $event)
    {
        try {
            $eventData = $this->eventRepository->unpublish($event, $request->all(), authUser()->id);

            return $this->successResponse(new EventResource($eventData), 'Successfully Unpublish', Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);

            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param Event $event
     *
     * @return JsonResponse
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function sendThankYou(Event $event): JsonResponse
    {
        $updatedEvent = EventRepository::sendThanksToAttendeesOf($event);

        return $this->successResponse(new EventResource($updatedEvent));
    }

    /**
     * @param Event $event
     *
     * @return JsonResponse
     *
     * @author John Dometita <john.d@ragingriverict.com>
     */
    public function sendNotification(Event $event): JsonResponse
    {
        $this->invitationRepository->sendInvite($event);
        EventRepository::sendEmailNotificationToAttendeesOf($event);
        return $this->successResponse($event, 'Notification sent');
    }

    /**
     * Set member as flagged by owner
     *
     * @param $request
     * @return JsonResponse
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function setFlagged(Request $request)
    {
        try {
            $data = $this->eventRepository->setFlagged($request->type, $request->event_id, $request->user_id, $request->media_id, $request->reason);
            return $this->successResponse($data, 'Flagged', Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the flagged status of the member
     *
     * @param $request
     * @return JsonResponse
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function unsetFlagged(Request $request){
        try {
            $data = $this->eventRepository->unsetFlagged($request->type, $request->event_id, $request->user_id);
            return $this->successResponse($data, 'Flagged', Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create an event name & location
     * Event name, event venue, event location
     *
     * @param $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function createNameLocation(EventNameLocationRequest $request) {
        try {
            $userId = authUser()->id;
            $data = $this->eventRepository->createNameLocation($request->all(), $userId);
            $result = (new EventStepsResource($data))->setStep(EventSteps::NAME_LOCATION);
            return $this->successResponse($result, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update event name, event venue, event location
     *
     * @param $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateNameLocation(EventNameLocationRequest $request, Event $event) {
        try {
            $data = $this->eventRepository->updateNameLocation($request->all(), $event);
            $result = (new EventStepsResource($data))->setStep(EventSteps::NAME_LOCATION);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update event interest, type and categories
     *
     * @param $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateTypeCategory(TypeCategoryInterestRequest $request, Event $event) {
        try {
            $data = $this->eventRepository->updateTypeCategory($request->all(), $event);
            $result = (new EventStepsResource($data))->setStep(EventSteps::TYPE_CATEGORY);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update event description
     *
     * @param Request $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateDescription(EventDescriptionRequest $request, Event $event){
        try {
            $data = $this->eventRepository->updateDescription($request->all(), $event);
            $result = (new EventStepsResource($data))->setStep(EventSteps::DESCRIPTION);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Event cover photo, previous event photo, event video
     * Event RSVP Type, Live chat enabled, Live chat type
     *
     * @param EventMediaRequest $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateMediaSetting(EventMediaRequest $request, Event $event){
        try {
            $data = $this->eventRepository->updateMediaSetting($request->all(), $event);
            $result = (new EventStepsResource($data))->setStep(EventSteps::MEDIA_SETTING);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update event Roles and responsibilities
     *
     * @param Request $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateRolesResponsibilities(EventRolesRequest $request, Event $event){
        try {
            $userId = authUser()->id;
            $data = $this->eventRepository->updateRolesResponsibilities($request->all(), $event, $userId);
            $result = (new EventStepsResource($data))->setStep(EventSteps::ROLES);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update event date, time, timezone
     *
     * @param EventDateTimeRequest $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateDateTime(EventDateTimeRequest $request, Event $event){
        try {
            $data = $this->eventRepository->updateDateTime($request->all(), $event);
            $result = (new EventStepsResource($data))->setStep(EventSteps::DATE_TIME);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Preview
     *
     * @param Request $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function preview(Request $request, Event $event){
        try {
            $result = (new EventStepsResource($event))->setStep(EventSteps::ALL);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function listInvitePastEvents(Request $request)
    {
        $perPage = 10;
        $keyword = $request->keyword;
        $user = User::find(authUser()->id);

        $result = $this->invitationRepository->listInvitePastEvents($user, $keyword, $perPage);

        return $this->successResponse(PastInviteResource::collection($result), NULL, Response::HTTP_OK, true );

    }

    public function listInviteFriends(Request $request, Event $event){

        $authUser = User::find(authUser()->id);

        $excludedUsers = $this->invitationRepository->getPastEventsUsers($event->id);

        $myFriends = $this->searchRepository->getFriendsOf($authUser, $request->event_ids ?? [], $excludedUsers);

        return $this->successResponse(UserSearchResource::collection($myFriends), NULL, Response::HTTP_OK, true);

    }


    /**
     * Invite people from your past events
     *
     * @param Request $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function invitePastEvents(Request $request, Event $event){
        try {
            $this->invitationRepository->invitePastEvents($request->all(), $event);
            return $this->successResponse(null, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update event step 9
     * Invite friends
     *
     * @param Request $request
     * @param Event $event
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function inviteFriends(Request $request, Event $event){
        try {

            $user = User::find(authUser()->id);

            if (isset($request['all']) && $request['all'] == 'true') {

                $excludedUsers = $this->invitationRepository->getPastEventsUsers($event);

                $friendToBeInvited = $this->searchRepository->getFriendsOf($user, [], $excludedUsers)->pluck('id')->toArray();

                $friendInvited =  $this->invitationRepository->inviteFriends($friendToBeInvited, $event, authUser()->id);

                return $this->successResponse($friendInvited, null, Response::HTTP_OK);

            }

            $friends =  $this->invitationRepository->inviteFriends($request->friend_ids, $event, authUser()->id);

            return $this->successResponse($friends, null, Response::HTTP_OK);

        } catch (\Exception $e) {

            \Log::error($e);

            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get event data steps
     * Check EventSteps enum how many steps are available
     *
     * @param int $step (step to get)
     * @param int $eventId
     *
     * @return EventStepsResource
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getStep(Request $request)
    {
        $userId  = authUser()->id;
        $step    = $request->step ?? 0;
        $eventId = $request->id ?? 0;
        $event   = $this->eventRepository->getEvent($eventId, $userId); // include user id to make sure it's the owner data

        $hasStep = collect(EventSteps::map())
                    ->flatten()
                    ->contains($step);
        $result = [];

        if ($hasStep) {
            $result = (new EventStepsResource($event))->setStep($step);
        }else{
            // if steps not found throw a 404 error
            abort(404);
        }

        return $result;
    }

    public function getPastEventsUsers(Request $request, Event $event){

        try {
            $userIds = $this->invitationRepository->getPastEventsUsers($event->id);
            return $this->successResponse($userIds, Response::HTTP_OK);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

    }



}
