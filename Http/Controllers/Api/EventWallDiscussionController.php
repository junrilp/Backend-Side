<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\Event;
use App\Enums\WallType;
use App\Models\EventAlbum;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Enums\DiscussionType;
use Illuminate\Http\Response;
use App\Models\EventAlbumItem;
use App\Events\WallPostedEvent;
use App\Traits\DiscussionTrait;
use Illuminate\Http\JsonResponse;
use App\Events\UserDiscussionEvent;
use Illuminate\Support\Facades\DB;
use App\Models\EventWallAttachment;
use App\Models\EventWallDiscussion;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\LikeResource;
use App\Models\EventWallDiscussionLike;
use App\Http\Resources\EventWallResource;
use App\Repository\Album\AlbumRepository;
use App\Http\Requests\LikeEventWallRequest;
use App\Http\Resources\UserBasicInfoResource;
use App\Http\Requests\DeleteLikeEventWallRequest;
use App\Repository\Discussions\DiscussionRepository;
use App\Http\Requests\StoreEventWallDiscussionRequest;
use App\Http\Requests\DeleteEventWallDiscussionRequest;
use App\Http\Requests\UpdateEventWallDiscussionRequest;
use App\Http\Requests\DeleteEventWallAttachmentDiscussionRequest;

class EventWallDiscussionController extends Controller
{
    use ApiResponser, DiscussionTrait;


    /**
     * Get all the data from database
     *
     * @param Request $request
     * @param int $entityId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request, $entityId)
    {

        try {
            $perPage = $request->limit ?? $request->perPage ?? 15;
            $eventWallDiscussion = EventWallDiscussion::where('entity_id', (int)$entityId)
                ->with('attachments.media', 'likes', 'recentLikers')
                ->whereDoesntHave('attachments', function ($query) {
                    $query->whereHas('media', function ($query) {
                        $query->where('processing_status', 1);
                    });
                })
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $result = EventWallResource::collection($eventWallDiscussion);
            return $this->successResponse($result, null, Response::HTTP_OK, true);

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get Specific discussion
     *
     * @param Request $request
     * @param int $entityId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getDiscussionById(Request $request, int $entityId)
    {
        try {

            $perPage = $request->limit ?? $request->perPage ?? 15;
            $limit = $request->limit ?? 10;
            $hasLimit = $request->limit ? true : false;
            $discussionId = $request->discussion_id ?? null;

            $sort = $request->sort ?? 'new-discussion';

            $wall = DiscussionRepository::getDiscussion(DiscussionType::EVENT_WALL, $entityId, $sort, $perPage, $limit, $hasLimit, $discussionId);

            if ($wall) {
                $result = EventWallResource::collection($wall);
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
     * @param EventWallDiscussion $wallDiscussion
     * @param StoreEventWallDiscussionRequest $request
     * @param int $entityId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(EventWallDiscussion $wallDiscussion, StoreEventWallDiscussionRequest $request, int $entityId)
    {
        try {

            $discussion = $wallDiscussion->create(DiscussionRepository::discussionForm($request->all(), DiscussionType::EVENT_WALL, $entityId, authUser()->id));

            if (isset($request->media_id)) {
                DiscussionRepository::updateMediaOnEventWallDiscussion($request->media_id, $discussion->id, $discussion->media_id, DiscussionType::EVENT_WALL);
            }


            if ($discussion) {
                // $discussion->load('wallAttachment');
                // $discussion->load('primaryAttachement');
                // $result = new EventWallResource($discussion);

                // $discussion = EventWallDiscussion::find($discussion->id);

                // if ($discussion->media_id == '' || $discussion->media_type != 'video') {
                //     Log::info('true');
                //     broadcast(new WallPostedEvent($discussion, authUser()->id));
                // } else {
                //     Log::info('false');
                // }


                $attachments = collect($request->attachments)->map(function ($mediaId) use ($discussion) {

                    //get the wall album
                    $wallAlbum = EventAlbum::where('is_wall', 1)->where('event_id', $discussion->entity_id)->first();

                    //create an entry on the album
                    if(!$wallAlbum) {
                        $wallAlbum = EventAlbum::create([
                            'is_wall' => 1,
                            'event_id' => $discussion->entity_id,
                            'user_id' => $discussion->user_id,
                            'name' => 'Wall Items'
                        ]);
                    }
                    EventAlbumItem::create([
                        'event_albums_id' => $wallAlbum->id,
                        'media_id' => $mediaId,
                        'user_id' => $wallAlbum->user_id
                    ]);

                    return new EventWallAttachment([
                        'media_id' => $mediaId,
                    ]);
                });

                $discussion->attachments()->saveMany($attachments);
                $discussion->load('attachments.media');

                $result = new EventWallResource($discussion);

                if (
                    $attachments->isEmpty() ||
                    !$attachments->contains(function ($attachment) {
                        return (int) $attachment->media->processing_status === 1;
                    })
                ) {

                    broadcast(new WallPostedEvent($discussion, WallType::EVENT, authUser()->id));
                }

                return $this->successResponse($result, null, Response::HTTP_CREATED);
            }

        } catch (Throwable $exception) {
            Log::critical('EventWallDiscussionContoller::store ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     *  Update the specified row.
     *
     * @param EventWallDiscussion $wallDiscussion
     * @param UpdateEventWallDiscussionRequest $request
     * @param int $entityId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(EventWallDiscussion $wallDiscussion, UpdateEventWallDiscussionRequest $request, int $entityId, int $id)
    {
        try {

            $getWall = $wallDiscussion->find($id);
            $checkPost = $getWall->update(DiscussionRepository::discussionForm($request->all(), DiscussionType::EVENT_WALL, $entityId, authUser()->id));

            if (isset($request->media_id)) {
                DiscussionRepository::updateMediaOnEventWallDiscussion($request->media_id, $id, (int) $getWall->media_id, DiscussionType::EVENT_WALL);
            }

            if ($checkPost) {
                $discussion = $wallDiscussion->find($id);
                $discussion->load('wallAttachment');

                $result = new EventWallResource($discussion);

                return $this->successResponse($result, null, Response::HTTP_OK);
            }

            return $this->successResponse('Discussion not found!');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteEventWallDiscussionRequest $deleteEventWall
     * @param int $entityId
     * @param int $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteEventWallDiscussionRequest $deleteEventWall, int $entityId, int $discussionId)
    {
        try {

            $hasWallAttachment = EventWallAttachment::where('event_wall_discussion_id', $discussionId);
            if ($hasWallAttachment->exists()) {
                $mediaIds = $hasWallAttachment->pluck('media_id')->all();

                $eventAlbum = EventAlbum::where('is_wall', 1)->where('event_id', $entityId)->first();
                if (!empty($eventAlbum)) {
                    foreach ($mediaIds as $mediaId) {
                        AlbumRepository::destroyAlbumAttachmentByMediaId(DiscussionType::EVENTS, (int) $eventAlbum->id, (int) $mediaId);
                    }
                }
            }

            $discussion = DiscussionRepository::destroyDiscussion(DiscussionType::EVENT_WALL, $entityId, $discussionId, authUser()->id);

            if ($discussion) {
                $result = new EventWallResource($discussion);
                return $this->successResponse($result, null, Response::HTTP_OK);
            }

            return $this->successResponse('Discussion not found!');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete singe attachement
     *
     * @param DeleteEventWallAttachmentDiscussionRequest $deleteEventWallAttachment
     * @param int $entityId
     * @param int $discussionId
     * @param int $attachmentId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function deleteSingleAttachment(DeleteEventWallAttachmentDiscussionRequest $deleteEventWallAttachment, int $entityId, int $discussionId, int $attachmentId)
    {
        try {

            $discussion = DiscussionRepository::deleteSinglePostEventWallAttachment($discussionId, $attachmentId);

            if ($discussion) {
                return $this->successResponse('Successfully removed!');
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param EventWallDiscussion $post
     *
     * @return JsonResponse
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    private function likeResponseFor (EventWallDiscussion $post): JsonResponse
    {
        $likeDetails = DiscussionRepository::getLikeDetailsOf($post);

        return $this->successResponse([
            'total_likes' => $likeDetails['count'],
            'people_like' => UserBasicInfoResource::collection($likeDetails['recentLikers'])
        ]);
    }

    /**
     * @param LikeEventWallRequest $likeUnlike
     * @param int $entityId
     * @param int $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function likePost(LikeEventWallRequest $likeUnlike, int $entityId, int $discussionId)
    {
        try {

            $likeUnlike = DiscussionRepository::likeUnlikeDiscussion($discussionId, authUser()->id, DiscussionType::EVENT_WALL);

            if ($likeUnlike) {
                return $this->likeResponseFor(EventWallDiscussion::find($discussionId));
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param EventWallDiscussionLike $like
     * @param int $entityId
     * @param int $discussionId
     * @param mixed $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function deleteLikePost(EventWallDiscussionLike $like, int $entityId, int $discussionId)
    {
        try {
            $likeId = EventWallDiscussion::find($discussionId)
                ->likes()
                ->where('user_id', authUser()->id)
                ->firstOrFail()
                ->id;

            $deleteLike = $like->find($likeId)->delete();

            if ($deleteLike) {
                return $this->likeResponseFor(EventWallDiscussion::find($discussionId));
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param mixed $eventId
     * @param mixed $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getLikePost($eventId, $discussionId)
    {
        try {

            $like = DiscussionRepository::getLikePost(DiscussionType::EVENT_WALL, $discussionId);

            if ($like) {
                $result = LikeResource::collection($like);
                return $this->successResponse($result);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function shareDiscussionWall(Request $request){
        $entityId = $request->entityId;
        $discussionId = $request->discussionId;
        $event = Event::find($entityId);
        event(new UserDiscussionEvent(authUser()->id, 'event_wall_shared', $event, $discussionId));
    }
}
