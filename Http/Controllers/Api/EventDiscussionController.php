<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\Event;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Enums\DiscussionType;
use Illuminate\Http\Response;
use App\Events\UserDiscussionEvent;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Jobs\EventGroupDiscussionJob;
use App\Http\Resources\DiscussionResource;
use App\Http\Requests\StoreDiscussionRequest;
use App\Http\Requests\DeleteDiscussionRequest;
use App\Http\Requests\UpdateDiscussionRequest;
use App\Repository\Discussions\DiscussionRepository;

class EventDiscussionController extends Controller
{

    use ApiResponser;


    /**
     * Get all the data from database
     *
     * @param Request $request
     * @param int $eventId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request, int $eventId)
    {
        try {

            $perPage = $request->limit ?? $request->perPage ?? 15;
            $limit = $request->limit ?? 10;
            $hasLimit = $request->limit ? true : false;

            $sort = $request->sort ?? 'new-discussion';

            $eventDiscussion = DiscussionRepository::getDiscussion(DiscussionType::EVENTS, $eventId, $sort, $perPage, $limit, $hasLimit);

            if ($eventDiscussion) {
                $result = DiscussionResource::collection($eventDiscussion);
                return $this->successResponse($result, null, Response::HTTP_OK, true);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get Specific discussion
     *
     * @param Request $request
     * @param int $eventId
     * @param int $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getDiscussionById(Request $request, int $eventId)
    {
        try {

            $perPage = $request->limit ?? $request->perPage ?? 15;
            $limit = $request->limit ?? 10;
            $hasLimit = $request->limit ? true : false;
            $discussionId = $request->discussion_id ?? null;

            $sort = $request->sort ?? 'new-discussion';

            $eventDiscussion = DiscussionRepository::getDiscussion(DiscussionType::EVENTS, $eventId, $sort, $perPage, $limit, $hasLimit, $discussionId);

            if ($eventDiscussion) {
                $result = DiscussionResource::collection($eventDiscussion);
                return $this->successResponse($result, null, Response::HTTP_OK, true);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Save new record.
     *
     * @param StoreDiscussionRequest $request
     * @param int $eventId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(StoreDiscussionRequest $request, int $eventId)
    {
        try {

            $isExisting = DiscussionRepository::checkExistingTitle(DiscussionType::EVENTS, $request->title, $eventId);

            if ($isExisting && !$request->proceedDuplicateTopic) {
                return $this->errorResponse('Discussion already exist', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $eventDiscussion = DiscussionRepository::newDiscussion(DiscussionType::EVENTS, $request->title, $request->discussion, $eventId, authUser()->id, $request->attachments);

            if ($eventDiscussion) {
                EventGroupDiscussionJob::dispatch($eventDiscussion, 'events')->onQueue('high');

                if (isset($request->postToWall) && (int)$request->postToWall === 1) {
                    $event = Event::find($eventDiscussion->entity_id);
                    event(new UserDiscussionEvent(authUser()->id, 'event_discussion_created', $event, $eventDiscussion->id));
                }

                $result = new DiscussionResource($eventDiscussion);
                return $this->successResponse($result, null, Response::HTTP_CREATED);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     *  Update the specified row.
     *
     * @param UpdateDiscussionRequest $request
     * @param int $type
     * @param int $eventId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateDiscussionRequest $request, int $eventId, int $id)
    {
        try {

            $isExisting = DiscussionRepository::checkExistingTitle(DiscussionType::EVENTS, $request->title, $eventId);

            if ($isExisting && !$request->proceedDuplicateTopic) {
                $eventDiscussion = DiscussionRepository::updateDiscussion(DiscussionType::EVENTS, $request->title, $request->discussion, $eventId, $id, authUser()->id);

                if ($eventDiscussion) {
                    $result = new DiscussionResource($eventDiscussion);
                    return $this->successResponse($result, null, Response::HTTP_OK);
                }
            } else {
                return $this->errorResponse('Discussion does not exist', Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteDiscussionRequest $deleteDiscussion
     * @param int $eventId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteDiscussionRequest $deleteDiscussion, int $eventId, int $id)
    {
        try {

            $eventDiscussion = DiscussionRepository::destroyDiscussion(DiscussionType::EVENTS, $eventId, $id, authUser()->id);

            if ($eventDiscussion) {
                $result = new DiscussionResource($eventDiscussion);
                return $this->successResponse($result, null, Response::HTTP_OK);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Share event discussion to wall
     */
    public function shareDiscussion(Request $request){
        $entityId = $request->entityId;
        $discussionId = $request->discussionId;
        $event = Event::find($entityId);
        event(new UserDiscussionEvent(authUser()->id, 'event_discussion_shared', $event, $discussionId));
    }
}
