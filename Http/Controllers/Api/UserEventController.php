<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\User;
use App\Enums\RSVPType;
use App\Events\UserDiscussionEvent;
use Exception;
use App\Models\UserEvent;
use App\Models\Conversation;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\EventResource;
use App\Http\Requests\UserEventRequest;
use App\Http\Resources\UserEventResource;
use App\Models\Event;
use App\Enums\TableLookUp;
use App\Repository\Event\EventRepository;
use App\Jobs\SendEventJoinRequestAsVipEmail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\Events\RSVPNewUserJoinedNotification;

class UserEventController extends Controller
{
    use ApiResponser;

    private $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /**
     * Store new record for UserEvent into user_events
     * @param UserEventRequest $request
     * @return UserEventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(UserEventRequest $request)
    {
        try {
            $data = $this->eventRepository->postUserEvent($request->all(), authUser()->id);

            $event = Event::whereId($data->event_id)
                ->withCount('userEvents as people_interested')
                ->with([
                    'attendees',
                    'eventTags'
                ])->first();

            if (isset($request->postToWall) && (int)$request->postToWall === 1) {
                event(new UserDiscussionEvent(authUser()->id, 'event_rsvpd', $event->makeHidden(['attendees', 'eventTags'])));
            }

            $result = new EventResource($event);

            /*
            * Send a notification to owner of the events that a new user joined the event
            * @author Junril Pateño <junril090693@gmail.com>
            */
            Notification::send(
                $event->host,
                new RSVPNewUserJoinedNotification($request->event_id, authUser(), $event)
            );

            if ($result->rsvp_type === 3) { // send an email to vip request
                $owner = collect($result);
                $eventDate =  Carbon::parse($result->event_start)->format('M d, Y');
                $moderatorName = "{$owner['owner']['first_name']} {$owner['owner']['last_name']}";
                $eventName = $result->title;
                $eventPhoto = getFullImage($result->primaryPhoto->location);

                $request = [
                    'photo'      => $eventPhoto,
                    'email'      => authUser()->email,
                    'event_name' => $eventName,
                    'recipient_name' => authUser()->first_name,
                    'body_text' => "Thank you for RSVP'ing to the {$eventName}.
                        Your RSVP does not guarantee admission into the event. We are reviewing your profile to make sure it is full and complete.
                        If your profile meets our standards and you are selected to attend you will receive a separate email with your ticket to this exclusive event.<br/><br/>
                        In the meantime, please feel free to tell a friend to complete a PerfectFriends profile and submit a request to be added to the {$eventName} VIP Guest list
                        as well.
                        I hope to see you on {$eventDate}. Please make sure your profile is full and complete for our review.<br/><br/>
                        Respectfully,<br/>
                        {$moderatorName}"
                ];

                SendEventJoinRequestAsVipEmail::dispatch($request)->onQueue('high');

            }
            return $this->successResponse($result, null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong. ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update specific UserEvent in user_events using id
     * @param UserEventRequest $request
     * @param mixed $id
     * @return UserEventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UserEventRequest $request, $id)
    {
        try {
            $data = $this->eventRepository->updateUserEvent($request->all(), authUser()->id, $id);

            if ($data) {
                $eventUser = UserEvent::whereId($id)
                    ->where('user_id', authUser()->id)->first();

                $data = Event::whereId($eventUser->event_id)
                    ->withCount('userEvents as people_interested')
                    ->with([
                        'attendees',
                        'eventTags'
                    ])->first();

                $result = new EventResource($data);

                return $this->successResponse($result);
            }

            return $this->errorResponse('Event not exist.', Response::HTTP_BAD_REQUEST);
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong. ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete UserEvent in user_events using id
     * @param mixed $id
     * @return UserEventResource collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(Request $request, $id)
    {
        $data = $this->eventRepository->deleteUserEvent($id, Auth::user()->id);

        if (!$data) {
            return $this->errorResponse('Did not match in any records.', Response::HTTP_BAD_REQUEST);
        }

        // Remove user for receiving a message to all members
        Conversation::where('receiver_id', $data->user_id)
            ->where('table_id', $data->event_id)
            ->where('table_lookup', TableLookUp::PERSONAL_MESSAGE_EVENTS)
            ->delete();

        if ($data->event->rsvp_type==RSVPType::LIMITED) {
            $this->eventRepository->updateLimitedCapacityCount($data->event);
        }


        //call function that will check the queue list

        $data = Event::whereId($data->event_id)
            ->withCount('userEvents as people_interested')
            ->with([
                'attendees',
                'eventTags'
            ])->first();

        $result = new EventResource($data);

        return $this->successResponse($result);
    }

    /**
     * Retreive all events created by login-user
     *
     * @param Request $request
     * @return EventResource Collection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getMyEvents(Request $request)
    {
        $data = $this->eventRepository->getMyEvents(authUser()->id, $request->all());

        if ($data == false) {
            return $this->successResponse([]);
        }

        $result = EventResource::collection($data);

        return $this->successResponse($result, null, Response::HTTP_OK, true);
    }

    /**
     * Get events of specified user
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Contracts\Routing\ResponseFactory|Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getUserEvents(Request $request, User $user)
    {
        $results = $this->eventRepository->getMyEvents($user->id, $request->all());
        if (!$results) {
            return $this->successResponse([]);
        }

        return $this->successResponse(EventResource::collection($results), null, Response::HTTP_OK, true);
    }
}
