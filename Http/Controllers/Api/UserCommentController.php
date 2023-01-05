<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Enums\DiscussionType;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\UserDiscussionComment;
use App\Http\Resources\CommentResource;
use App\Http\Requests\AddCommentRequest;
use App\Http\Requests\DeleteCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Repository\Comment\CommentRepository;

class UserCommentController extends Controller
{
    use ApiResponser;
    
    /**
     * Get Comments
     * 
     * @param Request $request
     * @param int $entityId
     * @param int $discussionId
     * @param null $parentId
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request, int $entityId, int $discussionId)
    {
        try {
            
            $page = ($request->perPage) ? $request->perPage : 10;
            $parentId = ($request->parent_id) ? $request->parent_id : null;
            
            $comments = CommentRepository::getComments(DiscussionType::WALL, $discussionId, $entityId, $parentId, $page);

            $result = CommentResource::collection($comments);

            return $this->successResponse($result, null, Response::HTTP_OK, true);
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Add Comments
     * 
     * @param AddCommentRequest $request
     * @param int $entityId
     * @param int $discussionId
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(AddCommentRequest $request, int $entityId, int $discussionId)
    {
        try {
            
            $comment = CommentRepository::addComments(DiscussionType::WALL, $entityId, $discussionId, $request->comment, authUser()->id, $request->parent_id != 0 ? $request->parent_id : null);

            if ($comment) {
                $result = new CommentResource($comment);
                return $this->successResponse($result, null, Response::HTTP_CREATED);
            }

        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Edit comments
     * 
     * @param UpdateCommentRequest $request
     * @param int $entityId
     * @param int $discussionId
     * @param int $commentId
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateCommentRequest $request, int $entityId, int $discussionId, int $commentId)
    {
        try {
            
            $comment = CommentRepository::updateComments(DiscussionType::WALL, $entityId, $discussionId, $request->comment, $commentId, authUser()->id, $request->parent_id != 0 ? $request->parent_id : null);

            if ($comment) {

                $result = new CommentResource(UserDiscussionComment::find($commentId));
                return $this->successResponse($result, null, Response::HTTP_OK);
            }
            
            return $this->successResponse('Comment not found!');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete comment
     * 
     * @param Request $request
     * @param int $entityId
     * @param int $discussionId
     * @param int $commentId
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DeleteCommentRequest $request, int $entityId, int $discussionId, int $commentId)
    {
        try {
            
            $page = ($request->perPage) ? $request->perPage : 10;
            
            $comments = CommentRepository::deleteComments(DiscussionType::WALL, $entityId, $commentId, $discussionId, authUser()->id, $page);
            
            if ($comments) {
                $result = new CommentResource($comments);
                return $this->successResponse($result, null, Response::HTTP_OK);
            }

            return $this->successResponse('Comment not found!');
        } catch (Throwable $exception) {
            Log::critical($exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
