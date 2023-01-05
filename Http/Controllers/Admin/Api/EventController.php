<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\User;
use App\Models\Event;
use App\Enums\EventSteps;
use App\Enums\GeneralStatus;
use App\Enums\GatheringType;
use App\Enums\TakeAction;
use App\Models\UserEvent;
use App\Enums\SearchMethod;
use App\Traits\AdminTraits;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Http\Resources\UserEventResource;
use App\Repository\Event\EventRepository;
use App\Http\Resources\EventStepsResource;
use App\Http\Resources\PastInviteResource;
use App\Http\Resources\UserSearchResource;
use App\Repository\Browse\BrowseRepository;
use App\Repository\Search\SearchRepository;
use App\Http\Requests\EventNameLocationRequest;
use App\Repository\Invitation\InvitationRepository;
use App\Http\Resources\AdminUserSummaryDetailResource;
use App\Http\Resources\AdminEventSummaryDetailResource;
use App\Http\Resources\AdminEventCompleteDetailResource;

class EventController extends Controller
{
    use ApiResponser, AdminTraits;

    private $eventRepository;
    public $browseRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;

    public function __construct(EventRepository $eventRepository, BrowseRepository $browseRepository)
    {
        $this->eventRepository = $eventRepository;
        $this->browseRepository = $browseRepository;
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
    }
    /**
     * list all events
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory AdminEventSummaryDetailResource
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function index(Request $request)
    {
        $events = Event::paginate(10);

        return $this->successResponse(AdminEventSummaryDetailResource::collection($events), '', Response::HTTP_OK, true);
    }

    /**
     * show event model
     * @param mixed $id
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory AdminEventCompleteDetailResource
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function show($id)
    {
        $event = Event::find($id);

        return $this->successResponse(new AdminEventCompleteDetailResource($event));
    }

    /**
     * show event attendees
     * @param mixed $id
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory AdminUserSummaryDetailResource
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function showRSVPd($id)
    {
        $event = Event::find($id);

        return $this->successResponse(AdminUserSummaryDetailResource::collection($event->attendees));
    }

    public function statistics(Request $request)
    {
        $events = Event::withTrashed()->when(isset($request->gathering_type), function ($q) use ($request) {
            return $q->whereGatheringType($request->gathering_type);
        })->get();

        $active = $events->where('is_published', 1)->where('status', GeneralStatus::PUBLISHED)->count();
        $deactivated = $events->where('status', GeneralStatus::DEACTIVATED)->count();
        $unpublished = $events->where('is_published', 0)->where('status', GeneralStatus::UNPUBLISHED)->count();
        $flagged = $events->where('status', GeneralStatus::FLAGGED)->count();
        $suspended = $events->where('status', GeneralStatus::SUSPENDED)->count();
        $deleted = $events->whereNotNull('deleted_at')->where('status', GeneralStatus::DELETED)->count();

        return $this->successResponse([
            'statistics' => [
                TakeAction::ACTIVE => $active,
                TakeAction::DEACTIVATED => $deactivated,
                TakeAction::UNPUBLISHED => $unpublished,
                TakeAction::FLAGGED => $flagged,
                TakeAction::SUSPENDED => $suspended,
                TakeAction::DELETED => $deleted
            ],
            'all' => $events->count()
        ]);
    }

    public function getEvent(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 25;
            $allMembers = '';
            if (!isset($request->search_type) && $request->search_type === "advance") {
                $searchFilter = $this->browseRepository->searchFilter($request->all());

                $allMembers = $this->browseRepository->elasticSearchAdminUser(
                    $searchFilter,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::ALL_MEMBERS
                );
            }

            $gatheringType = $request->gathering_type;

            $eventQuery = Event::with([
                'eventUser', 'media',
                'eventNotes' => function ($q) use ($gatheringType) {
                    ($gatheringType == GatheringType::EVENT) ? $q->event() : $q->huddle();
                }
            ])
                ->whereGatheringType($gatheringType)
                ->when(!empty($allMembers), function ($q) use ($allMembers) {
                    return $q->whereIn('user_id', $allMembers->pluck('id'));
                });

            if (!in_array(trim(strtolower($request->page_type)), [TakeAction::EDIT, TakeAction::PUBLISH])) {
                // return deleted items if not in array condition
                $eventQuery->withTrashed();
            }

            // Search Filter conditions
            $eventQuery->when($request->search_type === 'header' && $request->search_field !== 'null', function ($q) use ($request) {
                return $q->where('title', 'like', '%' . $request->search_field . '%');
            })->when($request->search_type === 'header' && $request->city_state !== 'null', function ($q) use ($request) {
                return $q->where('description', 'like', '%' . $request->city_state . '%');
            });

            // Page type filter conditions
            $eventQuery
                ->when($request->page_type === TakeAction::PUBLISH, function ($q) {
                    return $q->where('status', GeneralStatus::UNPUBLISHED);
                })
                ->when($request->page_type === TakeAction::FLAG, function ($q) {
                    return $q->whereIn("status", [GeneralStatus::PUBLISHED]);
                })
                ->when($request->page_type === TakeAction::UNSUSPEND, function ($q) {
                    return $q->where("status", GeneralStatus::SUSPENDED);
                })
                ->when($request->page_type === TakeAction::SUSPEND, function ($q) {
                    return $q->whereIn("status", [GeneralStatus::PUBLISHED, GeneralStatus::FLAGGED]);
                })
                ->when($request->page_type === TakeAction::DEACTIVATE, function ($q) {
                    return $q->whereIn("status", [GeneralStatus::PUBLISHED, GeneralStatus::FLAGGED, GeneralStatus::SUSPENDED]);
                })
                ->when($request->page_type === TakeAction::REACTIVATE, function ($q) {
                    return $q->whereIn("status", [GeneralStatus::SUSPENDED, GeneralStatus::DEACTIVATED]);
                })
                ->when($request->page_type === TakeAction::DELETE, function ($q) {
                    return $q->where("status", "<>", GeneralStatus::DELETED);
                })
                ->when($request->page_type === TakeAction::REMOVE_FLAG, function ($q) {
                    return $q->where("status", GeneralStatus::FLAGGED);
                });

            // Statistics Page Filter condition
            $eventQuery
                ->when($request->page_filter === TakeAction::ACTIVE, function ($q) {
                    return $q->where('status', GeneralStatus::PUBLISHED);
                })
                ->when($request->page_filter === TakeAction::DEACTIVATED, function ($q) {
                    return $q->where('status', GeneralStatus::DEACTIVATED);
                })
                ->when($request->page_filter === TakeAction::UNPUBLISHED, function ($q) {
                    return $q->where('status', GeneralStatus::UNPUBLISHED);
                })
                ->when($request->page_filter === TakeAction::FLAGGED, function ($q) {
                    return $q->where('status', GeneralStatus::FLAGGED);
                })
                ->when($request->page_filter === TakeAction::SUSPENDED, function ($q) {
                    return $q->where('status', GeneralStatus::SUSPENDED);
                })
                ->when($request->page_filter === TakeAction::DELETED, function ($q) {
                    return $q->where('status', GeneralStatus::DELETED);
                });

            $eventList = $eventQuery->paginate($perPage);

            return $this->successResponse(EventResource::collection($eventList), '', Response::HTTP_OK, true);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something is wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }


    public function getUserEventStatistics($userId)
    {

        $userEvent = UserEvent::with('event')->where('user_id', $userId);
        $all = $userEvent->count();
        $active = $userEvent->whereHas('event', function ($builder) {
            $builder->where('is_published', 1);
        })->count();

        $unpublished = UserEvent::with('event')->where('user_id', $userId)
            ->whereHas('event', function ($builder) {
                $builder->where('is_published', 0);
            })->count();

        $flagged = UserEvent::with('event')->where('user_id', $userId)
            ->whereHas('event', function ($builder) {
                $builder->where('status', GeneralStatus::FLAGGED);
            })->count();
        $suspended = UserEvent::with('event')->where('user_id', $userId)
            ->whereHas('event', function ($builder) {
                $builder->where('status', GeneralStatus::SUSPENDED);
            })->count();
        $deactivated = UserEvent::with('event')->where('user_id', $userId)
            ->whereHas('event', function ($builder) {
                $builder->where('status', GeneralStatus::DEACTIVATED);
            })->count();
        $deleted = $userEvent->whereHas('event', function ($builder) {
            $builder->whereNotNull('deleted_at');
        })->count();

        $statistics = [
            'All' => $all,
            'active' => $active - ($flagged + $suspended + $deleted + $deactivated),
            'deactivated' => $deactivated,
            'unpublished' => $unpublished,
            'flagged' => $flagged,
            'suspended' => $suspended,
            'deleted' => $deleted
        ];

        return $this->successResponse($statistics);
    }

    public function getuserEvents($userId, Request $request)
    {
        try {
            $ids = [];
            if (!isset($request->advance_search) or !isset($request->custom_search)) {
                if (!isset($request->advance_search)) {
                    $options = $request->all();
                }
                if (!isset($request->custom_search)) {
                    $options['city'] = $request->city_state;
                    $options['state'] = $request->city_state;
                }

                $data = $this->eventRepository->search(
                    $this->eventRepository->loadEvents(
                        $request->all(),
                        $userId,
                        'all'
                    ),
                    false,
                    false
                );

                foreach ($data as $datas) {
                    array_push($ids, $datas->id);
                }
            }

            $userEvent = UserEvent::with(['event', 'report'])
                ->when($request->type === TakeAction::ACTIVE, function ($q) {
                    return $q->whereHas('event', function ($builder) {
                        $builder->where('is_published', 1)->where('status', 1);
                    });
                })
                ->when($request->type === GeneralStatus::UNPUBLISHED, function ($q) {
                    return $q->whereHas('event', function ($builder) {
                        $builder->where('is_published', 0);
                    });
                })
                ->when($request->type === GeneralStatus::DELETED, function ($q) {
                    return $q->whereHas('event', function ($builder) {
                        $builder->whereNotNull('deleted_at');
                    });
                })
                ->when($request->type === GeneralStatus::FLAGGED, function ($q) {
                    return $q->whereHas('event', function ($builder) {
                        $builder->where('status', GeneralStatus::FLAGGED);
                    });
                })
                ->when($request->type === GeneralStatus::SUSPENDED, function ($q) {
                    return $q->whereHas('event', function ($builder) {
                        $builder->where('status', GeneralStatus::SUSPENDED);
                    });
                })
                ->when($request->type === GeneralStatus::DEACTIVATED, function ($q) {
                    return $q->whereHas('event', function ($builder) {
                        $builder->where('status', GeneralStatus::DEACTIVATED);
                    });
                })
                ->when(!isset($request->advance_search) or !isset($request->custom_search), function ($q) use ($ids) {
                    return $q->whereIn('event_id', $ids);
                })
                ->where('user_id', $userId)
                ->get();

            return $this->successResponse(UserEventResource::collection($userEvent));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something is wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getEventBySlug(Request $request)
    {
        $event = Event::where('slug', $request->slug)->first();

        $subpages = [
            [
                'link' => "/event/$event->slug",
                'label' => 'Members',
            ],
            [
                'link' => "/event/$event->slug/wall",
                'label' => 'Wall',
            ],
            [
                'link' => "/event/$event->slug/discussion-board",
                'label' => 'Discussion Board',
            ],
            [
                'link' => "/event/$event->slug/albums",
                'label' => 'Albums',
                'count' => $event->albums_count,
            ],
        ];

        return $this->successResponse(['event' => new EventResource($event), 'subpages' => $subpages], 'Event successfully show.', Response::HTTP_OK);
    }

    public function createNameLocation(EventNameLocationRequest $request)
    {
        try {
            $userId = authUser()->id;
            $data = $this->eventRepository->createNameLocation($request->all(), $userId);
            $result = (new EventStepsResource($data))->setStep(EventSteps::NAME_LOCATION);
            return $this->successResponse($result, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function updateNameLocation(EventNameLocationRequest $request, Event $event)
    {
        try {
            $data = $this->eventRepository->updateNameLocation($request->all(), $event);
            $result = (new EventStepsResource($data))->setStep(EventSteps::NAME_LOCATION);
            return $this->successResponse($result, Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function editNameLocation(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::NAME_LOCATION);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditCategoryInterest', ['event' => $event]),
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }

    public function editCategoryInterest(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::TYPE_CATEGORY);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditDescription', ['event' => $event]),
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editDescription(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::DESCRIPTION);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditMedia', ['event' => $event]),
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editMedia(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::MEDIA_SETTING);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditAdmin', ['event' => $event]),
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editAdmin(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::ROLES);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'nextPageUrl' => $event->is_published || url()->previous() === route('eventReviewAndPublish', ['event' => $event]) ? route('eventReviewAndPublish', ['event' => $event]) : route('eventEditSchedule', ['event' => $event]),
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function editSchedule(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::DATE_TIME);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'nextPageUrl' => route('eventReviewAndPublish', ['event' => $event]),
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function reviewAndPublish(Event $event)
    {
        $formData = (new EventStepsResource($event))->setStep(EventSteps::ALL);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'form' => $formData,
                'isPublished' => (bool) $event->is_published,
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }
    public function invitePastEvents(InvitationRepository $invitationRepository, Request $request, Event $event)
    {
        $perPage = 10;
        $keyword = $request->keyword;
        $user = User::find(authUser()->id);

        $result = $invitationRepository->listInvitePastEvents($user, $keyword, $perPage);


        $publishedJustNow = false;
        // $timeNow = time();
        // if ((strtotime($event->created_at) + (60 * 5)) <= $timeNow) {
        //     $publishedJustNow = false;
        // }

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'pastEvents' => PastInviteResource::collection($result),
                'publishedJustNow' => $publishedJustNow,
                'gatheringType' => $event->gathering_type
            ],
            'Event successfully show.',
            Response::HTTP_ACCEPTED
        );
    }

    public function inviteFriends(Request $request, SearchRepository $searchRepository, InvitationRepository $invitationRepository, Event $event)
    {
        $authUser = User::find(authUser()->id);

        $excludedUsers = $invitationRepository->getPastEventsUsers($event); //get all the invited users

        //get all friends excluding the invited users
        $myFriends = $searchRepository->getFriendsOf($authUser, $request->event_ids ?? [], $excludedUsers, 10);

        return $this->successResponse(
            [
                'id' => $event->id,
                'slug' => $event->slug,
                'myFriends' => UserSearchResource::collection($myFriends)
            ],
            'Group invite friends show.',
            Response::HTTP_ACCEPTED
        );
    }
}
