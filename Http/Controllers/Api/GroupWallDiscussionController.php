<?php

namespace App\Http\Controllers\Api;

use App\Enums\DiscussionType;
use App\Enums\WallType;
use App\Events\WallPostedGroup;
use Illuminate\Http\JsonResponse;
use App\Events\UserDiscussionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteEventWallAttachmentDiscussionRequest;
use App\Http\Requests\LikeGroupWallRequest;
use App\Http\Requests\StoreGroupWallDiscussionRequest;
use App\Http\Requests\UpdateGroupWallDiscussionRequest;
use App\Http\Requests\DeleteEventWallDiscussionRequest;
use App\Http\Requests\DeleteGroupWallDiscussionRequest;
use App\Http\Resources\GroupWallResource;
use App\Http\Resources\LikeResource;
use App\Http\Resources\UserBasicInfoResource;
use App\Models\Group;
use App\Models\GroupAlbum;
use App\Models\GroupAlbumItem;
use App\Models\GroupWallDiscussion;
use App\Models\GroupWallDiscussionLike;
use App\Repository\Discussions\DiscussionRepository;
use App\Traits\ApiResponser;
use App\Traits\DiscussionTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Models\GroupWallAttachment;
use App\Repository\Album\AlbumRepository;

class GroupWallDiscussionController extends Controller
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

            $groupWallDiscussion = GroupWallDiscussion::where('entity_id', (int)$entityId)
                ->with('attachments.media', 'likes', 'recentLikers')
                ->whereDoesntHave('attachments', function ($query) {
                    $query->whereHas('media', function ($query) {
                        $query->where('processing_status', 1);
                    });
                })
                ->orderByDesc('created_at')
                ->paginate($perPage);

            $result = GroupWallResource::collection($groupWallDiscussion);
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

            $wall = DiscussionRepository::getDiscussion(DiscussionType::GROUP_WALL, $entityId, $sort, $perPage, $limit, $hasLimit, $discussionId);

            if ($wall) {
                $result = GroupWallResource::collection($wall);
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
     * @param GroupWallDiscussion $wallDiscussion
     * @param StoreGroupWallDiscussionRequest $request
     * @param int $entityId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(GroupWallDiscussion $wallDiscussion, StoreGroupWallDiscussionRequest $request, int $entityId)
    {

        try {

            $discussion = $wallDiscussion->create(DiscussionRepository::discussionForm($request->all(), DiscussionType::GROUP_WALL, $entityId, authUser()->id));

            if (isset($request->media_id)) {
                DiscussionRepository::updateMediaOnEventWallDiscussion($request->media_id, $discussion->id, $discussion->media_id, DiscussionType::GROUP_WALL);
            }


            if ($discussion) {
                // $discussion->load('wallAttachment');
                // $discussion->load('primaryAttachement');
                // $result = new GroupWallResource($discussion);

                // $discussion = GroupWallDiscussion::find($discussion->id);

                // if ($discussion->media_id == '' || $discussion->media_type != 'video') {
                //     Log::info('GroupWallDiscussionController::store about to broadcast');
                //     broadcast(new WallPostedGroup($discussion, WallType::GROUP , authUser()->id));
                //     Log::info('GroupWallDiscussionController::store');
                // } else {
                //     Log::info('false');
                // }

                $attachments = collect($request->attachments)->map(function ($mediaId) use ($discussion)  {

                     //get the wall album
                     $wallAlbum = GroupAlbum::where('is_wall', 1)->where('group_id', $discussion->entity_id)->first();

                     //create an entry on the album
                     if(!$wallAlbum) {
                         $wallAlbum = GroupAlbum::create([
                             'is_wall' => 1,
                             'group_id' => $discussion->entity_id,
                             'user_id' => $discussion->user_id,
                             'name' => 'Wall Items'
                         ]);
                     }
                     GroupAlbumItem::create([
                         'group_albums_id' => $wallAlbum->id,
                         'media_id' => $mediaId,
                         'user_id' => $wallAlbum->user_id
                     ]);

                    return new GroupWallAttachment([
                        'media_id' => $mediaId,
                    ]);
                });

                $discussion->attachments()->saveMany($attachments);
                $discussion->load('attachments.media');

                $result = new GroupWallResource($discussion);

                if (
                    $attachments->isEmpty() ||
                    !$attachments->contains(function ($attachment) {
                        return (int) $attachment->media->processing_status === 1;
                    })
                ) {
                    broadcast(new WallPostedGroup($discussion, WallType::GROUP, authUser()->id));
                }

                return $this->successResponse($result, null, Response::HTTP_CREATED);
            }

        } catch (Throwable $exception) {
            Log::critical('GroupWallDiscussionController::store ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     *  Update the specified row.
     *
     * @param GroupWallDiscussion $wallDiscussion
     * @param UpdateGroupWallDiscussionRequest $request
     * @param int $entityId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(GroupWallDiscussion $wallDiscussion, UpdateGroupWallDiscussionRequest $request, int $entityId, int $id)
    {
        try {

            $getWall = $wallDiscussion->find($id);
            $checkPost = $getWall->update(DiscussionRepository::discussionForm($request->all(), DiscussionType::EVENT_WALL, $entityId, authUser()->id));

            if (isset($request->media_id)) {
                DiscussionRepository::updateMediaOnEventWallDiscussion($request->media_id, $id, (int) $getWall->media_id, DiscussionType::GROUP_WALL);
            }

            if ($checkPost) {
                $discussion = $wallDiscussion->find($id);
                $discussion->load('wallAttachment');

                $result = new GroupWallResource($discussion);

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
     * @param DeleteGroupWallDiscussionRequest $deleteEventWall
     * @param int $entityId
     * @param int $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteGroupWallDiscussionRequest $deleteEventWall, int $entityId, int $discussionId)
    {
        Log::debug($deleteEventWall);
        try {

            $hasWallAttachment = GroupWallAttachment::where('group_wall_discussion_id', $discussionId);
            if ($hasWallAttachment->exists()) {
                $mediaIds = $hasWallAttachment->pluck('media_id')->all();

                $groupAlbum = GroupAlbum::where('is_wall', 1)->where('group_id', $entityId)->first();
                if (!empty($groupAlbum)) {
                    foreach ($mediaIds as $mediaId) {
                        AlbumRepository::destroyAlbumAttachmentByMediaId(DiscussionType::GROUPS, (int) $groupAlbum->id, (int) $mediaId);
                    }
                }
            }

            $discussion = DiscussionRepository::destroyDiscussion(DiscussionType::GROUP_WALL, $entityId, $discussionId, authUser()->id);

            if ($discussion) {
                $result = new GroupWallResource($discussion);
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
     * @param GroupWallDiscussion $post
     *
     * @return JsonResponse
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    private function likeResponseFor (GroupWallDiscussion $post): JsonResponse
    {
        $likeDetails = DiscussionRepository::getLikeDetailsOf($post);

        return $this->successResponse([
            'total_likes' => $likeDetails['count'],
            'people_like' => UserBasicInfoResource::collection($likeDetails['recentLikers'])
        ]);
    }

    /**
     * @param LikeGroupWallRequest $likeUnlike
     * @param int $entityId
     * @param int $discussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function likePost(LikeGroupWallRequest $likeUnlike, int $entityId, int $discussionId)
    {
        try {

            $likeUnlike = DiscussionRepository::likeUnlikeDiscussion($discussionId, authUser()->id, DiscussionType::GROUP_WALL);

            if ($likeUnlike) {
                return $this->likeResponseFor(GroupWallDiscussion::find($discussionId));
            }else{
                return $this->errorResponse('This post has been deleted. ', Response::HTTP_BAD_REQUEST);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param GroupWallDiscussionLike $like
     * @param int $entityId
     * @param int $discussionId
     * @param mixed $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function deleteLikePost(GroupWallDiscussionLike $like, int $entityId, int $discussionId)
    {
        try {
            $likeId = GroupWallDiscussion::find($discussionId)
                ->likes()
                ->where('user_id', authUser()->id)
                ->firstOrFail()
                ->id;

            $deleteLike = $like->find($likeId)->delete();

            if ($deleteLike) {
                return $this->likeResponseFor(GroupWallDiscussion::find($discussionId));
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
        $group = Group::find($entityId);
        event(new UserDiscussionEvent(authUser()->id, 'group_wall_shared', $group, $discussionId));
    }
}
