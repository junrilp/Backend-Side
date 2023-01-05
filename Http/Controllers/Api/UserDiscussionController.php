<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Media;
use App\Events\WallPosted;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Enums\DiscussionType;
use Illuminate\Http\Response;
use App\Models\UserDiscussion;
use App\Traits\DiscussionTrait;
use App\Enums\UserDiscussionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\WallResource;
use App\Http\Requests\WallPostRequest;
use App\Http\Requests\WallUpdatRequest;
use App\Models\UserDiscussionAttachment;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Requests\DeleteUserDiscussionRequest;
use App\Repository\Discussions\DiscussionRepository;
use App\Http\Requests\DeleteUserDiscussionAttachmentRequest;
use App\Http\Resources\UserBasicInfoResource;
use Illuminate\Http\JsonResponse;
use App\Models\UserDiscussionLike;

class UserDiscussionController extends Controller
{
    use ApiResponser, DiscussionTrait;


    /**
     * Get all the data from database
     *
     * @param Request $request
     * @param int $userId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request, int $userId)
    {
        try {
            $user = User::findOrFail($userId);
            return $this->getWallPost($request->all(), $user);

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get Specific discussion
     *
     * @param Request $request
     * @param int $userId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getDiscussionById(Request $request, int $userId)
    {
        try {

            $discussionId = $request->discussion_id;

            $wall = DiscussionRepository::getDiscussionById(DiscussionType::WALL, $userId, $discussionId);

            if ($wall) {
                $result = new WallResource($wall);
                return $this->successResponse($result);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Save new record.
     *
     * @param UserDiscussion $wallDiscussion
     * @param WallPostRequest $request
     * @param int $userId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(UserDiscussion $wallDiscussion, WallPostRequest $request, int $userId)
    {
        try {

            $discussion = $wallDiscussion->create(UserDiscussion::postForm($request->all(), $userId));

            if (isset($request->media_id)) {
                DiscussionRepository::updateMediaOnUserDiscussion($request->media_id, $discussion->id, $discussion->media_id);
            }
            /*
            Will comment this for reference in the future if ever we need to separate the video and other attachment
            if (isset($request->attachments) || $request->media_id) {
                // if there is an attachment to this post it will run inside our event
                // If ther is media_id which is a video it will be uploaded using event
                event(new FileWasUploadedEvent($request->attachments, $request->media_id,  $discussion->id));
            }
            */

            if ($discussion) {
                // $discussion->load('wallAttachment');
                // $discussion->load('primaryAttachement');
                // // $data->loadCount(['interests as people_interested', 'likes as total_likes']); Will uncommented this once it should implemented
                // $result = new WallResource($discussion);
                // $discussion = UserDiscussion::find($discussion->id);

                // Log::info($discussion);
                // Log::info('user discussion controller');
                // Log::info($discussion->media_id != null && $discussion->media_type != 'video');
                // $test = $discussion->media_id != null && $discussion->media_type != 'video';
                // Log::info($test);
                // Log::info($discussion->media_id);
                // Log::info($discussion->media_type);



                $attachments = collect($request->attachments)->map(function ($mediaId) {
                    return new UserDiscussionAttachment([
                        'media_id' => $mediaId,
                    ]);
                });

                $discussion->attachments()->saveMany($attachments);
                $discussion->load('attachments.media');

                $result = new WallResource($discussion);

                if (
                    $attachments->isEmpty() ||
                    !$attachments->contains(function ($attachment) {
                        return (int) $attachment->media->processing_status === 1;
                    })
                ) {
                    broadcast(new WallPosted($discussion));
                }

                return $this->successResponse($result, null, Response::HTTP_CREATED);
            }

        } catch (Throwable $exception) {
            Log::critical('UserDiscussionController::store ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     *  Update the specified row.
     *
     * @param UserDiscussion $wallDiscussion
     * @param WallUpdatRequest $request
     * @param int $userId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UserDiscussion $wallDiscussion, WallUpdatRequest $request, int $userId, int $id)
    {
        try {
            $checkPost = $wallDiscussion->find($id)->update(UserDiscussion::postForm($request->all(), $userId));

            if (isset($request->media_id)) {
                DiscussionRepository::updateMediaOnUserDiscussion($request->media_id, $id, $wallDiscussion->media_id);
            }
            /*
            Will comment this for reference in the future if ever we need to separate the video and other attachment
            if (isset($request->attachments) || isset($request->video)) {
                // if there is an attachment to this post it will run inside our event
                // If ther is media_id which is a video it will be uploaded using event
                event(new FileWasUploadedEvent($request->attachments, $request->video, $id));
            }
            */

            if ($checkPost) {
                $discussion = $wallDiscussion->find($id);
                $discussion->load('wallAttachment');

                $result = new WallResource($discussion);

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
     * @param DeleteUserDiscussionRequest $DeleteUserDiscussionRequest
     * @param int $userId
     * @param int $userDiscussionId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteUserDiscussionRequest $DeleteUserDiscussionRequest, int $userId, int $userDiscussionId)
    {
        try {

            $discussion = DiscussionRepository::destroyDiscussion(DiscussionType::WALL, $userId, $userDiscussionId, authUser()->id);

            if ($discussion) {
                $result = new WallResource($discussion);
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
     * @param DeleteUserDiscussionAttachmentRequest $DeleteUserDiscussionAttachmentRequest
     * @param int $userId
     * @param int $discussionId
     * @param int $attachmentId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function deleteSingleAttachment(DeleteUserDiscussionAttachmentRequest $DeleteUserDiscussionAttachmentRequest, int $userId, int $discussionId, int $attachmentId)
    {
        try {

            $discussion = DiscussionRepository::deleteSinglePostAttachment($discussionId, $attachmentId);

            if ($discussion) {
                return $this->successResponse('Successfully removed!');
            }

            return $this->successResponse('Comment not found!');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }


    /**
     * @param array $request
     *
     * @return array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getWallPost(array $request = [], User $user)
    {
        $wallPosts = UserDiscussion::where(function ($query) use ($user) {
                $query->postsOf($user);
                if (authCheck()) {
                    $authUser = User::find(authUser()->id);
                    $query->orWhere->includeFriendPostsOf($authUser);
                }
            })
            ->orderByDesc('user_discussions.created_at')
            ->paginate($request['perPage'] ?? 10);

        return $this->successResponse(WallResource::collection($wallPosts), null, Response::HTTP_OK, true);
    }

    /**
     * @param Collection $collection
     * @param int $perPage
     * @param string $pageName
     *
     * @return array
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    private static function paginateCollection(Collection $collection, int $perPage, string $pageName = 'page')
    {
        $currentPage = LengthAwarePaginator::resolveCurrentPage($pageName);
        $currentPageItems = $collection->slice(($currentPage - 1) * $perPage, $perPage);
        $paginator = new LengthAwarePaginator(
            $currentPageItems->values(),
            $collection->count(),
            $perPage,
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]
        );

        return [
            "success" => true,
            "data" => $paginator->items(),
            "meta" => [
                    "current_page" => $paginator->currentPage(),
                    "from" => $paginator->firstItem(),
                    "last_page" => $paginator->lastPage(),
                    "links" => $paginator->linkCollection()->toArray(),
                    "path" => $paginator->path(),
                    "per_page" => $paginator->perPage(),
                    "to" => $paginator->lastItem(),
                    "total" => $paginator->total()
            ],
            "links" => [
                "first" => $paginator->url(1),
                "last" => $paginator->url($paginator->lastPage()),
                "prev" => $paginator->previousPageUrl(),
                "next" => $paginator->nextPageUrl()
            ],
        ];
    }

    private static function getEventGroupForDiscussion($discussion)
    {
        $entityId = $discussion->entity_id;
        $wallOwnerId = $discussion->user_id;

        $createdAt = $discussion->created_at;
        $updatedAt = $discussion->updated_at;
        $discussionType = $discussion->type;
        $discussionId = $discussion->id;

        if ($discussionType === UserDiscussionType::EVENT_PUBLISHED || $discussionType === UserDiscussionType::EVENT_RSVPD) {
            $model = Event::class;
        }
        if ($discussionType === UserDiscussionType::GROUP_CREATED || $discussionType === UserDiscussionType::GROUP_JOINED) {
            $model = Group::class;
        }

        return $model::selectedColumnForWall()
                ->addSelect(DB::raw("'$discussionType' as 'type'"))
                ->addSelect(DB::raw("'$discussionId' as 'id'"))
                ->addSelect(DB::raw("'$entityId' as 'entity_id'"))
                ->addSelect(DB::raw("'$wallOwnerId' as 'user_id'"))
                ->addSelect(DB::raw("'$createdAt' as 'created_at'"))
                ->addSelect(DB::raw("'$updatedAt' as 'updated_at'"))
                ->where('id', $entityId)
                ->first();
    }

    private static function getDiscussionForWall(string $type, $discussion)
    {
        $entityId = $discussion->entity_id;
        $wallOwnerId = $discussion->user_id;

        $createdAt = $discussion->created_at;
        $updatedAt = $discussion->updated_at;
        $discussionType = $discussion->type;
        $discussionId = $discussion->id;

        $getModel = DiscussionTrait::getDiscussionTrait($type);

        return $getModel::selectedColumnForWall()
                ->addSelect(DB::raw("'$discussionType' as 'type'"))
                ->addSelect(DB::raw("'$discussionId' as 'id'"))
                ->addSelect(DB::raw("'$entityId' as 'entity_id'"))
                ->addSelect(DB::raw("'$wallOwnerId' as 'user_id'"))
                ->addSelect(DB::raw("'$createdAt' as 'created_at'"))
                ->addSelect(DB::raw("'$updatedAt' as 'updated_at'"))
                ->where('id', $entityId)
                ->first();
    }

    /**
     * @param UserDiscussion $post
     *
     * @return JsonResponse
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    private function likeResponseFor (UserDiscussion $post): JsonResponse
    {
        $likeDetails = DiscussionRepository::getLikeDetailsOf($post);

        return $this->successResponse([
            'total_likes' => $likeDetails['count'],
            'people_like' => UserBasicInfoResource::collection($likeDetails['recentLikers'])
        ]);
    }

    public function likePost(int $entityId, int $discussionId)
    {
        try {

            $likeUnlike = DiscussionRepository::likeUnlikeDiscussion($discussionId, authUser()->id, DiscussionType::WALL);

            if ($likeUnlike) {
                return $this->likeResponseFor(UserDiscussion::find($discussionId));
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function deleteLikePost(int $entityId, int $discussionId)
    {
        try {
            $likeId = UserDiscussion::find($discussionId)
                ->likes()
                ->where('user_id', authUser()->id)
                ->firstOrFail()
                ->id;

            // dd($likeId);

            $deleteLike = UserDiscussionLike::find($likeId)->delete();

            if ($deleteLike) {
                return $this->likeResponseFor(UserDiscussion::find($discussionId));
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
