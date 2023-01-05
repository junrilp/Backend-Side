<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\Group;
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

class GroupDiscussionController extends Controller
{
    use ApiResponser;


    /**
     * Get all the data from database
     *
     * @param Request $request
     * @param int $groupId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request, int $groupId)
    {
        try {

            $perPage = $request->limit ?? $request->perPage ?? 15;
            $limit = $request->limit ?? 10;
            $hasLimit = $request->limit ? true : false;

            $sort = $request->sort ?? 'new-discussion';

            $groupDiscussion = DiscussionRepository::getDiscussion(DiscussionType::GROUPS, $groupId, $sort, $perPage, $limit, $hasLimit);

            if ($groupDiscussion) {
                $result = DiscussionResource::collection($groupDiscussion);
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
     * @param int $groupId
     * @param int $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getDiscussionById(Request $request, int $groupId, int $discussionId)
    {
        try {

            $perPage = $request->limit ?? $request->perPage ?? 15;
            $limit = $request->limit ?? 10;
            $hasLimit = $request->limit ? true : false;

            $sort = $request->sort ?? 'new-discussion';

            $groupDiscussion = DiscussionRepository::getDiscussion(DiscussionType::GROUPS, $groupId, $sort, $perPage, $limit, $hasLimit, $discussionId);

            if ($groupDiscussion) {
                $result = DiscussionResource::collection($groupDiscussion);
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
     * @param int $groupId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(StoreDiscussionRequest $request, int $groupId)
    {
        try {

            $isExisting = DiscussionRepository::checkExistingTitle(DiscussionType::GROUPS, $request->title, $groupId);

            if ($isExisting && !$request->proceedDuplicateTopic) {
                return $this->errorResponse('Discussion already exist', Response::HTTP_UNPROCESSABLE_ENTITY);
            }


            $groupDiscussion = DiscussionRepository::newDiscussion(DiscussionType::GROUPS, $request->title, $request->discussion, $groupId, authUser()->id, $request->attachments);

            if ($groupDiscussion) {

                EventGroupDiscussionJob::dispatch($groupDiscussion, 'groups')->onQueue('high');

                if (isset($request->postToWall) && (int)$request->postToWall === 1) {
                    $group = Group::find($groupDiscussion->entity_id);
                    event(new UserDiscussionEvent(authUser()->id, 'group_discussion_created', $group, $groupDiscussion->id));
                }

                $result = new DiscussionResource($groupDiscussion);
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
     * @param int $groupId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateDiscussionRequest $request, int $groupId, int $id)
    {
        try {

            $groupDiscussion = DiscussionRepository::updateDiscussion(DiscussionType::GROUPS, $request->title, $request->discussion, $groupId, $id, authUser()->id);

            if ($groupDiscussion) {
                $result = new DiscussionResource($groupDiscussion);
                return $this->successResponse($result, null, Response::HTTP_OK);
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
     * @param int $groupId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteDiscussionRequest $deleteDiscussion, int $groupId, int $id)
    {
        try {

            $groupDiscussion = DiscussionRepository::destroyDiscussion(DiscussionType::GROUPS, $groupId, $id, authUser()->id);

            if ($groupDiscussion) {
                $result = new DiscussionResource($groupDiscussion);
                return $this->successResponse($result, null, Response::HTTP_OK);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Share group discussion to wall
     */
    public function shareDiscussion(Request $request){
        $entityId = $request->entityId;
        $discussionId = $request->discussionId;
        $group = Group::find($entityId);
        event(new UserDiscussionEvent(authUser()->id, 'group_discussion_shared', $group, $discussionId));
    }
}

