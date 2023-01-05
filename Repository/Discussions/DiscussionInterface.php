<?php

namespace App\Repository\Discussions;

use App\Models\EventWallDiscussion;

interface DiscussionInterface
{
    public static function getDiscussion(string $type, int $entityId, string $sort, int $perPage, int $limit, bool $hasLimit = false, int $discussionId = null);

    public static function newDiscussion(string $type, string $title, string $discussion, int $entityId, int $userId);

    public static function updateDiscussion(string $type, string $title, string $discussion, int $entityId, int $id, int $userId);

    public static function destroyDiscussion(string $type, int $entityId, int $id, int $userId);

    public static function sendEventGroupDiscussionEmail(object $discussion = null, string $type);
  
    public static function deleteSinglePostAttachment(int $wallId, int $attachmentId);

    public static function createWallPost(int $userId, string $type, int $entityId = null, string $eventGroupUserForm = '');

    public static function deleteWallPost(int $userId, string $type, int $entityId = null);

    public static function updateMediaOnUserDiscussion($media, int $userDiscussionId, int $discussionMediaId = null);

    public static function updateMediaOnEventWallDiscussion($media, int $userDiscussionId, int $discussionMediaId = null, String $type);

    public static function discussionForm(array $discussion = [], string $type, int $entityId, int $userId);

    public static function deleteSinglePostEventWallAttachment(int $wallId, int $attachmentId);

    public static function likeUnlikeDiscussion(int $entityId, int $userId, string $type);

    public static function getLikePost(string $type, int $discussionId);

    public static function getLikeDetailsOf(EventWallDiscussion $post);

    public static function checkExistingTitle(string $type, string $title, int $entityId);
    
    public static function getDiscussionById(string $type, int $entityId, int $discussionId);
}
