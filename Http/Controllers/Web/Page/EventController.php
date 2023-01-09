<?php

namespace App\Http\Controllers\Web\Page;

use App\Enums\DiscussionType;
use App\Enums\EventSteps;
use App\Enums\GatheringType;
use App\Enums\TableLookUp;
use App\Events\UserDiscussionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\EditEventRequest;
use App\Http\Requests\EditEventAdminRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\EventAttendeeResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventStepsResource;
use App\Http\Resources\Media as MediaResource;
use App\Http\Resources\PastInviteResource;
use App\Http\Resources\UserSearchResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\Timezone;
use App\Models\Type;
use App\Models\User;
use App\Repository\Album\AlbumRepository;
use App\Repository\Event\EventRepository;
use App\Repository\Invitation\InvitationRepository;
use App\Repository\QrCode\QrCodeRepository;
use App\Repository\Search\SearchRepository;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use App\Enums\UserStatus;

class EventController extends Controller
{
    /**
     * Index page
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index(): Response
    {

        return Inertia::render('Events/Events', [
            'title' => 'Find and attend Perfect Friends Events and Huddles posted by members',
            'meta' => 'Find Perfect Friends Events and Huddles posted by members'
        ]);
    }

    /**
     * Create event page
     *
     * @return Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function create(Request $request): Response
    {
        $gatheringType = $request->type ?? GatheringType::EVENT;

        return Inertia::render(
            'Events/Create',
            array_merge($this->getCommonData(), [
                'title' => $gatheringType === GatheringType::HUDDLE ? '- Create huddle' : '- Create event',
                'gatheringType' => (int) $gatheringType,
            ])
        );
    }

    /**
     * @return array
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    private function getCommonData(): array
    {
        return [
            'eventTypes' => Type::query()->where('status', 1)->get(['id', 'name'])->toArray(),
            'categories' => Category::query()->where('status', 1)->get(['id', 'name'])->toArray(),
            /* TODO: Should be on API together with other options API */
            'timezones' => Timezone::select('id', 'label AS value')
                ->orderBy('offset')
                ->get(),
        ];
    }

    private function getEventDetails(Event $event)
    {

        $gatheringType = $event->gathering_type == GatheringType::EVENT ? 'Event' : 'Huddle';

        return collect([
            'title' => function () use ($event) {
                return '- '.$event->title;
            },
            'meta' => function () use ($event, $gatheringType) {
                $fullname = $event->eventUser->full_name;
                $datetime = $event->event_start && $event->start_time ? Carbon::createFromFormat('Y-m-d H:i:s', "$event->event_start $event->start_time")
                ->format('M d, Y h:ia') : '';

                return "Perfect Friends $gatheringType - $fullname $event->title - $datetime at $event->location View $gatheringType Information (" . url("events/$event->slug") . ")";
            },
            'bannerPhoto' => function () use ($event) {
                return $event->primaryPhoto ? (new MediaResource($event->primaryPhoto))
                    ->response()
                    ->getData()
                    ->data
                    ->sizes
                    ->lg : '';
            },
            'bannerBackground' => function () use ($event) {
                return '/images/event-single-cover.png';
            },
            'event' => function () use ($event) {
                $event->load([
                    'attendees',
                    'eventUser',
                ])->loadCount('userEvents as people_interested');

                return json_decode(response((new EventResource($event)))->content(), true);
            },
            'subPages' => function () use ($event) {
                return [
                    [
                        'link' => "/events/$event->slug",
                        'label' => 'People Attending',
                    ],
                    [
                        'link' => "/events/$event->slug/wall",
                        'label' => 'Wall',
                    ],
                    [
                        'link' => "/events/$event->slug/discussion-board",
                        'label' => 'Discussion Board',
                    ],
                    [
                        'link' => "/events/$event->slug/albums",
                        'label' => 'Albums',
                        'count' => $event->albums_count,
                    ],
                ];
            },
            'relatedEvents' => function (EventRepository $eventRepository) use ($event) {
                $relatedEvents = $eventRepository->getOtherEvents($event->id);

                return EventResource::collection($relatedEvents);
            },
            'unreadChat' => function() use ($event) {
                $userId = authUser()->id ?? 0;
                return
                    $event->conversation ?
                        $event
                            ->conversation
                            ->chats()
                            ->whereRaw("(NOT FIND_IN_SET(?, seen_by) OR seen_by IS NULL)", [$userId])
                            ->count()
                    : 0;

            }
        ]);
    }

    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function notFound(Request $request): RedirectResponse
    {
        $slug = $request->route()->parameters('slug')['event'];
        $event = Event::withTrashed()
            ->where('slug', $slug)
            ->first();

        if ($event->gathering_type === GatheringType::EVENT) {
            session()->flash('flash', [
                'code' => 'EVENT_NOT_FOUND',
            ]);
        } elseif ($event->gathering_type === GatheringType::HUDDLE) {
            session()->flash('flash', [
                'code' => 'HUDDLE_NOT_FOUND',
            ]);
        }

        return redirect('/events?search_type=past_events');
    }

    /**
     * Event page
     *
     * @param Request $request
     * @param Event $event
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function show(Request $request, Event $event): Response
    {
        $isJoining = !!$request->get('auto-join');
        
        if (
            // Disable temporarily
            // (
            //     $event->isPast &&
            //     !(auth()->check() && $event->user_id === auth()->id())
            // ) ||
            (
                !$event->isPublished() &&
                !(auth()->check() && $event->user_id === auth()->id())
            )
        ) {
            return $this->index();
        }
        
        if (!!$request->get('auto-join') && authCheck()) {
            EventRepository::postUserEvent(['event_id' => $event->id], authUser()->id);

            if ($request->get('postToWall') === 'true' && authCheck()) {
                $event = EventRepository::getEventBySlug($event->slug); // Will used this so that we can stor also the people_interested on our extras column
                event(new UserDiscussionEvent(authUser()->id, 'event_rsvpd', $event->makeHidden(['userEvents', 'userEvents.user', 'attendees', 'eventTags'])));
            }

            if (authUser()->status !== UserStatus::PUBLISHED) {
                request()->session()->flash('flash', [
                    'code' => 'EVENT_JOIN_INCOMPLETE_PROFILE'
                ]);
            }
        }

        return Inertia::render(
            'Events/Event2',
            $this->getEventDetails($event)->merge([
                'content' => function (EventRepository $eventRepository) use ($event, $request) {

                    $rsvpdUsers = $eventRepository->getAttendees($event->slug, $request->all(), $request->perPage ?? 20, $request->event_rsvp_status ?? 0);

                    return [
                        'members' => $rsvpdUsers ? EventAttendeeResource::collection($rsvpdUsers) : null,
                        'hasMorePages' => $rsvpdUsers ? $rsvpdUsers->hasMorePages() : false,
                        'queryParams' => $request->all()
                    ];
                },
                'isJoining' => $isJoining
            ])
        );
    }

    public function discussionBoard(Event $event)
    {
        return Inertia::render(
            'Events/Event2',
            $this->getEventDetails($event)->merge([
                'content' => function () {
                    return [
                        'discussionBoard' => null,
                    ];
                },
            ])
        );
    }

    public function wall(Event $event): Response
    {
        return Inertia::render(
            'Events/Event2',
            $this->getEventDetails($event)->merge([
                'content' => function () {
                    return [
                        'wall' => null,
                    ];
                },
            ])
        );
    }


    /**
     * Returns the album section
     */
    public function albums(Event $event): Response
    {
        return Inertia::render(
            'Events/Event2',
            $this->getEventDetails($event)->merge([
                'content' => function () {
                    return [
                        'albums' => null,
                    ];
                },
            ])
        );
    }

/**
     * Returns the album by id
     */
    public function albumById(Event $event, $albumId): Response
    {
        $album = AlbumRepository::getAlbumById(DiscussionType::EVENTS, $albumId);
        return Inertia::render(
            'Events/Event2',
            $this->getEventDetails($event)->merge([
                'content' => function () use ($album) {
                    return [
                        'albums' => null,
                        'album' => new AlbumResource($album),
                    ];
                },
            ])
        );
    }

    /**
     * Preview an event (DRAFT status)
     *
     * @param Request $request
     * @param Event $event
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function previewEvent(Event $event): Response
    {
        return Inertia::render(
            'Events/Event2',
            $this->getEventDetails($event)->merge([
                'subPages' => [],
                'isPreview' => true,
            ])
        );
    }

    /**
     * Edit an event
     *
     * @param EditEventRequest $request
     * @param Event $event
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function editEvent(EditEventRequest $request, Event $event): RedirectResponse
    {
        return redirect("/events/{$event->slug}/edit/review-and-publish");
    }

    public function editNameLocation(EditEventRequest $request, Event $event): Response
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::NAME_LOCATION);

        return Inertia::render('Events/EditNameLocation', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditCategoryInterest', ['event' => $event]),
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function editCategoryInterest(EditEventRequest $request, Event $event): Response
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::TYPE_CATEGORY);

        return Inertia::render('Events/EditCategoryInterest', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditDescription', ['event' => $event]),
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function editDescription(EditEventRequest $request, Event $event): Response
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::DESCRIPTION);

        return Inertia::render('Events/EditDescription', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditMedia', ['event' => $event]),
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function editMedia(EditEventRequest $request, Event $event): Response
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::MEDIA_SETTING);

        return Inertia::render('Events/EditMedia', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditAdmin', ['event' => $event]),
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function editAdmin(EditEventAdminRequest $request, Event $event)
    {

        $formData = (new EventStepsResource($event))->setStep(EventSteps::ROLES);

        return Inertia::render('Events/EditAdmin', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditSchedule', ['event' => $event]),
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function editSchedule(EditEventRequest $request, Event $event): Response
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::DATE_TIME);

        return Inertia::render('Events/EditSchedule', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'nextPageUrl' => route('eventReviewAndPublish', ['event' => $event]),
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function reviewAndPublish(EditEventRequest $request, Event $event): Response
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::ALL);

        return Inertia::render('Events/ReviewAndPublish', [
            'id' => $event->id,
            'slug' => $event->slug,
            'form' => $formData,
            'isPublished' => (bool) $event->is_published,
            'gatheringType' => $event->gathering_type
        ]);
    }

    /**
     * @param Request $request
     * @param Event $event
     *
     * @return RedirectResponse
     */
    public function publishSuccessful(Request $request, Event $event): RedirectResponse
    {
        if (!(bool) $event->is_published) {
            return redirect("/events/{$event->slug}/edit");
        }

        $request->session()->flash('publishedJustNow', true);

        return redirect("/events/{$event->slug}/invite-past-events");
    }

    public function invitePastEvents(EditEventRequest $request, InvitationRepository $invitationRepository, Event $event): Response
    {
        $perPage = 10;
        $keyword = $request->keyword;
        $user = User::find(authUser()->id);

        $result = $invitationRepository->listInvitePastEvents($user, $keyword, $perPage);

        return Inertia::render('Events/InvitePastEvents', [
            'id' => $event->id,
            'slug' => $event->slug,
            'pastEvents' => PastInviteResource::collection($result),
            'publishedJustNow' => $request->session()->get('publishedJustNow') === true,
            'gatheringType' => $event->gathering_type
        ]);
    }

    public function inviteFriends(EditEventRequest $request, SearchRepository $searchRepository, InvitationRepository $invitationRepository, Event $event): Response
    {

        $authUser = User::find(authUser()->id);

        $excludedUsers = $invitationRepository->getPastEventsUsers($event); //get all the invited users

        //get all friends excluding the invited users
        $myFriends = $searchRepository->getFriendsOf($authUser, $request->event_ids ?? [], $excludedUsers, 10);

        return Inertia::render('Events/InviteFriends', [
            'id' => $event->id,
            'slug' => $event->slug,
            'myFriends' => UserSearchResource::collection($myFriends)
        ]);

    }

    /**
     * @param string $qrCode
     *
     * @return Response
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function ticketConfirmation(string $qrCode): Response
    {
        $details = QrCodeRepository::getQrCodeJson($qrCode);

        return Inertia::render('Events/TicketConfirmation', array_merge(
            [
                'title' => '- Ticket Confirmation',
                'user' => $details['user'],
                'event' => $details['event'],
                'attendedAt' => $details['attended_at'],
            ]
        ));
    }
}
