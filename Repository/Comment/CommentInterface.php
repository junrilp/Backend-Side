<?php

namespace App\Repository\Comment;

interface CommentInterface
{
    public static function getComments(string $type, int $discussionId, int $entityId, int $parentId = null, int $page);

    public static function addComments(string $type, int $entityId, int $discussionId, string $comment, int $userId, int $parentId = null);

    public static function updateComments(string $type, int $entityId, int $discussionId, string $comment, int $commentId, int $userId, int $parentId = null);

    public static function deleteComments(string $type, int $entityId, int $commentId, int $discussionId, int $userId, int $page);
}
