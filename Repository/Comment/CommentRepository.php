<?php

namespace App\Repository\Comment;

use App\Traits\DiscussionTrait;
use App\Enums\DiscussionType;

class CommentRepository implements CommentInterface
{
    use DiscussionTrait;

    /**
     * Get comments
     *
     * @param string $type
     * @param int $discussionId
     * @param int $entityId
     * @param int|null $parentId | Can use to get specific comment under comment
     * @param int $page
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getComments(string $type, int $discussionId, int $entityId, int $parentId = null, int $page)
    {
        $model = DiscussionTrait::getCommentTrait($type);

        return $model::where('discussion_id', $discussionId)
        ->where('entity_id', $entityId)
        ->where('parent_id', $parentId)
        ->orderBy('id', 'DESC')
        ->paginate($page);
    }

    /**
     * Add comments
     *
     * @param string $type
     * @param int $entityId
     * @param int $discussionId
     * @param string $comment
     * @param int $userId
     * @param int|null $parentId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function addComments(string $type, int $entityId, int $discussionId, string $comment, int $userId, int $parentId = null, $attachments = [])
    {
        $getModel = DiscussionTrait::getCommentTrait($type);

        $comment = $getModel::create(self::filterCommentData($entityId, $discussionId, $comment, $userId, $parentId));

        // Attachments
        $media = collect($attachments)->map(function($mediaId){
            return [
                'media_id' => $mediaId
            ];
        });

        $comment->addMedia($media);

        return $comment;
    }

    /**
     * Edit comments
     *
     * @param string $type
     * @param int $entityId
     * @param int $discussionId
     * @param string $comment
     * @param int $commentId
     * @param int $userId
     * @param int|null $parentId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateComments(string $type, int $entityId, int $discussionId, string $comment, int $commentId, int $userId, int $parentId = null)
    {

        $getModel = DiscussionTrait::getCommentTrait($type);

        $getModel::whereId($commentId)
            ->where('discussion_id', $discussionId)
            ->where('entity_id', $entityId)
            ->where('user_id', $userId)
            ->update(self::filterCommentData($entityId, $discussionId, $comment, $userId, $parentId));

        return true;
    }

    /**
     * Delete comments
     *
     * @param string $type
     * @param int $entityId
     * @param int $commentId
     * @param int $discussionId
     * @param int $userId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function deleteComments(string $type, int $entityId, int $commentId, int $discussionId, int $userId, int $page)
    {

        $getModel = DiscussionTrait::getCommentTrait($type);

        $discussion = $getModel::whereId($commentId)
            // ->where('user_id', $userId)
            ->where('discussion_id', $discussionId)
            ->where('entity_id', $entityId);

        $copyDiscussion = $discussion->first();

        $discussion->delete();

        return $copyDiscussion;
    }

    /**
     * Initialize forms
     *
     * @param int $entityId
     * @param int $discussionId
     * @param string $comment
     * @param int $userId
     * @param int|null $parentId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    private static function filterCommentData(int $entityId, int $discussionId, string $comment, int $userId, int $parentId = null) {
        $data = [];
        if (isset($entityId)) {
            $data['entity_id'] = $entityId;
        }

        if (isset($discussionId)) {
            $data['discussion_id'] = $discussionId;
        }

        if (isset($comment)) {
            $data['comment'] = $comment;
        }

        if (isset($userId)) {
            $data['user_id'] = $userId;
        }

        if (isset($parentId)) {
            $data['parent_id'] = $parentId;
        }

        return $data;
    }
}
