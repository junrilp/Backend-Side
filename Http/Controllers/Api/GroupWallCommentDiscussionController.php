<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Traits\ApiResponser;
use App\Enums\DiscussionType;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Http\Requests\AddCommentRequest;
use App\Models\GroupWallDiscussionComment;
use App\Http\Requests\DeleteCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Repository\Comment\CommentRepository;

class GroupWallCommentDiscussionController extends Controller
{
    use ApiResponser;

    public function index(Request $request, int $entityId, int $discussionId)
    {
        try {

            $page = ($request->perPage) ? $request->perPage : 10;
            $parentId = ($request->parent_id) ? $request->parent_id : null;

            $comments = CommentRepository::getComments(DiscussionType::GROUP_WALL, $discussionId, $entityId, $parentId, $page);

            $result = CommentResource::collection($comments);

            return $this->successResponse($result, null, Response::HTTP_OK, true);
        } catch (Throwable $exception) {
           \Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(AddCommentRequest $request, int $entityId, int $discussionId)
    {
        try {

            $comment = CommentRepository::addComments(DiscussionType::GROUP_WALL, $entityId, $discussionId, $request->comment ?? '', authUser()->id, $request->parent_id != 0 ? $request->parent_id : null, $request->attachments);

            if ($comment) {
                $result = new CommentResource($comment);
                return $this->successResponse($result, null, Response::HTTP_CREATED);
            }

        } catch (Throwable $exception) {
            \Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(UpdateCommentRequest $request, int $entityId, int $discussionId, int $commentId)
    {
        try {

            $comment = CommentRepository::updateComments(DiscussionType::GROUP_WALL, $entityId, $discussionId, $request->comment, $commentId, authUser()->id, $request->parent_id != 0 ? $request->parent_id : null);

            if ($comment) {

                $result = new CommentResource(GroupWallDiscussionComment::find($commentId));
                return $this->successResponse($result, null, Response::HTTP_OK);
            }

        } catch (Throwable $exception) {
            \Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function destroy(DeleteCommentRequest $request, int $entityId, int $discussionId, int $commentId)
    {
        try {

            $page = ($request->perPage) ? $request->perPage : 10;

            $comments = CommentRepository::deleteComments(DiscussionType::GROUP_WALL, $entityId, $commentId, $discussionId, authUser()->id, $page);

            if ($comments) {
                $result = new CommentResource($comments);
                return $this->successResponse($result, null, Response::HTTP_OK);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
