<?php

namespace App\Repository\Discussions;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Media;
use App\Enums\MediaTypes;
use App\Models\UserEvent;
use App\Models\UserGroup;
use App\Enums\DiscussionType;
use App\Models\UserDiscussion;
use App\Models\EventDiscussion;
use App\Models\GroupDiscussion;
use App\Notifications\GroupStartedDiscussionNotification;
use App\Traits\DiscussionTrait;
use App\Enums\UserDiscussionType;
use App\Models\EventAlbum;
use App\Models\EventAlbumItem;
use App\Models\EventWallAttachment;
use App\Models\EventWallDiscussion;
use App\Models\GroupWallAttachment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use App\Models\UserDiscussionAttachment;
use App\Repository\Media\MediaRepository;
use Illuminate\Support\Facades\DB;
use App\Mail\EventGroupDiscussionNotification;

class DiscussionRepository implements DiscussionInterface
{
    use DiscussionTrait;

    /**
     * Get discussion
     *
     * @param string $type
     * @param int $entityId
     * @param string $sort
     * @param int $perPage
     * @param int $limit
     * @param bool $hasLimit
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getDiscussion(string $type, int $entityId, string $sort = null, int $perPage = null, int $limit = null, bool $hasLimit = false, int $discussionId = null)
    {
        $getModel = DiscussionTrait::getDiscussionTrait($type);

        $query = $getModel::withCount('comments as comments_count');

        if (!empty($entityId) && $entityId !== 0) {
            $query->where('entity_id', $entityId);
        }

        if (!empty($discussionId)) {
            $query->where('id', $discussionId);
        }

        if ($sort == 'hot-topics') {
            $query->orderByDesc('comments_count');
        } else {
            $query->orderBy('created_at', 'Desc');
        }

        if ($hasLimit) {
            return $query->limit($limit)->paginate($perPage);
        }

        return $query->paginate($perPage); //view more page
    }

    /**
     * Save new discussion
     *
     * @param string $title
     * @param string $discussion
     * @param string $type
     * @param int $entityId
     * @param int $userId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function newDiscussion(string $type, string $title, string $discussion, int $entityId, int $userId, $attachments = [])
    {
        $getModel = DiscussionTrait::getDiscussionTrait($type);

        $discussion = $getModel::create(self::filterDiscussionData($title, $discussion, $entityId, $userId));

        // Attachments
        $media = collect($attachments)->map(function($mediaId){
            return [
                'media_id' => $mediaId
            ];
        });

        $discussion->addMedia($media);

        if ($getModel == 'App\Models\GroupDiscussion') {

            $user = User::find($userId);
            $group = Group::find($entityId);

            Notification::send($discussion, new GroupStartedDiscussionNotification($discussion, $group, $user));
        }

        return $discussion;
    }

    /**
     * Update row discussion
     *
     * @param string $title
     * @param string $discussion
     * @param string $type
     * @param int $entityId
     * @param int $id
     * @param int $userId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateDiscussion(string $type, string $title, string $discussion, int $entityId, int $id, int $userId)
    {
        $getModel = DiscussionTrait::getDiscussionTrait($type);

        $getDiscussion =  $getModel::whereId($id);
        $discussion = $getDiscussion->where('user_id', $userId)
            ->where('entity_id', $entityId)
            ->update(self::filterDiscussionData($title, $discussion, $entityId, $userId));

        return $getDiscussion->first();
    }

    /**
     * Remove discussion
     * @param string $type
     * @param int $entityId
     * @param int $id
     * @param int $userId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function destroyDiscussion(string $type, int $entityId, int $id, int $userId)
    {
        $getModel = DiscussionTrait::getDiscussionTrait($type);

        $discussion = $getModel::whereId($id);

        $copyDiscussion = $discussion->first();

        $discussion->delete();

        return $copyDiscussion;
    }

    /**
     * Initialize event form
     * @param string $title
     * @param string $discussion
     * @param int $entityId
     * @param int $userId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    private static function filterDiscussionData(string $title, string $discussion, int $entityId, int $userId)
    {
        $data = [];
        if (isset($title)) {
            $data['title'] = $title;
        }

        if (isset($discussion)) {
            $data['discussion'] = $discussion;
        }

        if (isset($entityId)) {
            $data['entity_id'] = $entityId;
        }

        if (isset($userId)) {
            $data['user_id'] = $userId;
        }

        return $data;
    }

    public static function sendEventGroupDiscussionEmail(object $discussion = null, string $type)
    {

        $data = null;
        $headerTop = 'A New Discussion Topic';
        if ($type === 'events') {
            $getEvent = Event::whereId($discussion->entity_id)
                    ->with(['primaryPhoto','eventUser'])
                    ->first();

            $userList = UserEvent::where('event_id', $discussion->entity_id)->with('user')->get();
            $user = User::whereId($discussion->user_id)->with('primaryPhoto')->first();
            $data = [
                'headerTop' => $headerTop,
                'header' => 'was created for your Event!',
                'photo' => getFileUrl($getEvent->primaryPhoto->location),
                'eventGroupName' => $getEvent->title,
                'topic' => $discussion->title,
                'userPhoto' => getFileUrl($user->primaryPhoto->location),
                'userName' => $user->full_name,
                'topicDescription' => $discussion->discussion,
                'discussionLink' => URL::to('events/'.$getEvent->slug.'/discussion-board')
            ];
        }

        if ($type === 'groups') {
            $getGroup = Group::whereId($discussion->entity_id)
                    ->with(['media','members'])
                    ->first();

            $userList = UserGroup::where('group_id', $discussion->entity_id)->with('user')->get();
            $user = User::whereId($discussion->user_id)->with('primaryPhoto')->first();
            $data = [
                'headerTop' => $headerTop,
                'header' => 'was created for your Group!',
                'photo' => getFileUrl($getGroup->media->modified),
                'eventGroupName' => $getGroup->name,
                'topic' => $discussion->title,
                'userPhoto' => getFileUrl($user->primaryPhoto->modified),
                'userName' => $user->full_name,
                'topicDescription' => $discussion->discussion,
                'discussionLink' => URL::to('groups/'.$getGroup->slug.'/discussion-board')
            ];
        }

        foreach ($userList as $row) {
            // Check if valid to receive an email
            if ($row->user->validTypeAccount) {
                Mail::to($row['user']['email'])->send(new EventGroupDiscussionNotification($data));
            }
        }
    }

    /**
     * Delete single attachment post
     *
     * @param int $wallId
     * @param int $attachmentId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function deleteSinglePostAttachment(int $wallId, int $attachmentId)
    {
        $userDiscussion = UserDiscussion::whereId($wallId)->where('media_id', $attachmentId);

        if ($userDiscussion->exists()) {
            return $userDiscussion->update([
                'media_id' => NULL
            ]);
        }

        $hasAttachment = UserDiscussionAttachment::whereId($attachmentId)->where('user_discussion_id', $wallId);

        if ($hasAttachment->exists()) {
            MediaRepository::unlinkMedia($hasAttachment->first()->media_id);
            $hasAttachment->delete();
        }

        return true;
    }

    //POST TO WALL
    /**
     * Save new record to user_discussion table
     *
     * @param int $userId
     * @param string $type
     * @param int|null $entityId
     *
     * @return object
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function createWallPost(int $userId, string $type, int $entityId = null, string $eventGroupUserForm = '')
    {
        $userDiscussion = UserDiscussion::where('user_id', $userId)->where('type', $type)->where('entity_id', $entityId);

        if ($userDiscussion->exists() && !in_array($type, [UserDiscussionType::EVENT_WALL_SHARED,
                        UserDiscussionType::EVENT_DISCUSSION_SHARED,
                        UserDiscussionType::GROUP_WALL_SHARED, UserDiscussionType::GROUP_DISCUSSION_SHARED])) {
            return true;
        }

        $data = [];

        if (isset($userId)) {
            $data['user_id'] = $userId;
        }
        if (isset($type)) {
            $data['type'] = $type;
        }
        if (isset($entityId)) {
            $data['entity_id'] = $entityId;
        }
        if (isset($eventGroupUserForm)) {

            $media = json_decode($eventGroupUserForm);

            if (collect([
                UserDiscussionType::EVENT_PUBLISHED, UserDiscussionType::EVENT_RSVPD,
                UserDiscussionType::EVENT_DISCUSSION_CREATED, UserDiscussionType::EVENT_DISCUSSION_SHARED,
                UserDiscussionType::EVENT_WALL_SHARED
            ])->contains($type)){
                $mediaId = (int)$media->image;
            }

            if (collect([
                UserDiscussionType::GROUP_CREATED, UserDiscussionType::GROUP_JOINED,
                UserDiscussionType::GROUP_DISCUSSION_CREATED, UserDiscussionType::GROUP_DISCUSSION_SHARED,
                UserDiscussionType::GROUP_WALL_SHARED
            ])->contains($type)){
                $mediaId = (int)$media->image_id;
            }

            $primary = Media::whereId($mediaId)->select('location')->first()->location;

            $array = [];
            foreach ($media as $key => $row) {
                if ($key === 'description') {
                    $array[$key] = str_replace(array("\\", '"',  "'"), '', $row);
                } else {
                    $array[$key] = $row;
                }
            }

            $array['primary_attachment'] = getFileUrl($primary);

            $result = $array;

            $discussion = [];
            if ($type === UserDiscussionType::EVENT_DISCUSSION_CREATED) {
                $eventDiscussion = EventDiscussion::find($entityId);
                $discussion = $eventDiscussion;
                $discussion['events'] = $array;
                // Fix where photo and slug are not showing in wall
                $discussion['primary_attachment'] = $array['primary_attachment'];
                $discussion['slug'] = "{$array['slug']}/discussion-board";
                $result = $discussion;
            }
            if ($type === UserDiscussionType::GROUP_DISCUSSION_CREATED) {
                $groupDiscussion = GroupDiscussion::find($entityId);
                $discussion = $groupDiscussion;
                $discussion['groups'] = $array;
                 // Fix where photo and slug are not showing in wall
                $discussion['primary_attachment'] = $array['primary_attachment'];
                $discussion['slug'] = "{$array['slug']}/discussion-board";
                $result = $discussion;
            }

            $data['extra'] = json_encode($result);
        }

        $discussion =  UserDiscussion::create($data);

        return $discussion;

    }

    /**
     * Delete Wall Post
     *
     * @param int $userId
     * @param string $type
     * @param int|null $entityId
     *
     * @return boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function deleteWallPost(int $userId, string $type, int $entityId = null)
    {
        $userDiscussion = UserDiscussion::where('user_id', $userId)->where('type', $type)->where('entity_id', $entityId);
        if ($userDiscussion->exists()) {
            return $userDiscussion->delete();
        }

        return true;
    }

    /**
     * @param mixed $media
     * @param int $userDiscussionId
     * @param int|null $discussionMediaId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateMediaOnUserDiscussion($media, int $userDiscussionId, int $discussionMediaId = null)
    {
        if (is_array($media)) {
            foreach ($media as $row) {
                if ((int)$row !== $discussionMediaId) {
                    // After the first array is set all the remaining attachment
                    // will be save in separate table as other attachment
                    UserDiscussionAttachment::create([
                        'user_discussion_id' => $userDiscussionId,
                        'media_id' => $row
                    ]);
                }
            }
        }
    }

    /**
     * @param mixed $media
     * @param int $userDiscussionId
     * @param int|null $discussionMediaId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateMediaOnEventWallDiscussion($media, int $userDiscussionId, int $discussionMediaId = null, $type)
    {
        Log::debug('updateMediaOnEventWallDiscussion');
        $field = DiscussionRepository::getDiscussionIdField($type);
        if (is_array($media)) {
            foreach ($media as $row) {
                if ((int)$row !== $discussionMediaId) {
                    // After the first array is set all the remaining attachment
                    // will be save in separate table as other attachment

                    if($type == DiscussionType::EVENT_WALL) {
                        EventWallAttachment::create([
                            $field => $userDiscussionId,
                            'media_id' => $row
                        ]);


                    } else if ($type == DiscussionType::GROUP_WALL) {
                        GroupWallAttachment::create([
                            $field => $userDiscussionId,
                            'media_id' => $row
                        ]);
                    }

                }
            }
        }
    }

    /**
     * @param array $discussion
     * @param string $type
     * @param int $entityId
     * @param int $userId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function discussionForm(array $discussion = [], string $type, int $entityId, int $userId)
    {
        $data = [];

        $data['user_id'] = $userId;

        if(isset($userId)) {
            $data['entity_id'] = $entityId;
        }

        if (isset($discussion['body'])) {
            $data['body'] = $discussion['body'];
        }

        $data['type'] = $type;

        if (isset($discussion['media_id'])) {
            if (is_array($discussion['media_id'])) {

                $getMedia = Media::find($discussion['media_id'][0]);
                // Save first array as primary attachment
                $data['media_id'] = $discussion['media_id'][0];
                $data['media_type'] =  $getMedia->media_type_id === MediaTypes::VIDEOS ? 'video' : 'image';

            } else {

                // if the media is not an array
                $data['media_id'] = $discussion['media_id'];

            }

            $getMedia = Media::find($data['media_id']);
            $data['media_type'] =  $getMedia->media_type_id === MediaTypes::VIDEOS ? 'video' : 'image';

        }

        return $data;
    }

    /**
     * Delete single attachment post
     *
     * @param int $wallId
     * @param int $attachmentId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function deleteSinglePostEventWallAttachment(int $wallId, int $attachmentId)
    {
        $userDiscussion = EventWallDiscussion::whereId($wallId)->where('media_id', $attachmentId);

        if ($userDiscussion->exists()) {
            return $userDiscussion->update([
                'media_id' => NULL
            ]);
        }

        $hasAttachment = EventWallAttachment::whereId($attachmentId)->where('event_wall_discussion_id', $wallId);

        if ($hasAttachment->exists()) {
            MediaRepository::unlinkMedia($hasAttachment->first()->media_id);
            $hasAttachment->delete();
        }

        return true;
    }

    /**
     * @param int $discussionId
     * @param int $userId
     * @param string $type
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function likeUnlikeDiscussion(int $discussionId, int $userId, string $type)
    {
        Log::debug('likeUnlikeDiscussion ' . $type);
        $getModel = DiscussionTrait::getLikeUnlikeModel($type);

        $likeUnlike = $getModel::where(DiscussionRepository::getDiscussionIdField($type), $discussionId)
            ->where('user_id', $userId);

        if ($likeUnlike->exists()) {
            return $likeUnlike->delete();
        }

        try {
            $like = $getModel::create([
                DiscussionRepository::getDiscussionIdField($type) => $discussionId,
                'user_id' => $userId
            ]);
        } catch (\Exception $e) {
            return false;
        }

        return $like;
    }

    /**
     * @param string $type
     * @param int $discussionId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getLikePost(string $type, int $discussionId)
    {
        $getModel = DiscussionTrait::getLikeUnlikeModel($type);

        return $getModel::where(DiscussionRepository::getDiscussionIdField($type), $discussionId)->get();
    }

    public static function getLikeDetailsOf($post)
    {
        return [
            'count' => $post->likesCount,
            'recentLikers' => $post->recentLikers,
        ];
    }

    private static function getDiscussionIdField($type)
    {
        $discussionIdName = '';

        if($type == DiscussionType::GROUP_WALL) {
            $discussionIdName = 'group_wall_discussion_id';
        } else if ($type == DiscussionType::EVENT_WALL) {
            $discussionIdName = 'event_wall_discussion_id';
        }  else if ($type == DiscussionType::WALL) {
            $discussionIdName = 'user_discussion_id';
        }

        return $discussionIdName;
    }

    public static function checkExistingTitle(string $type, string $title, int $entityId)
    {
        $getModel = DiscussionTrait::getDiscussionTrait($type);

        return $getModel::where(strtolower('title'), strtolower($title))->where('entity_id', $entityId)->exists();
    }

    /**
     * This method will be used once someone need to access a specific
     * discussion from events or group by {discussion_id}
     *
     * @param string $type : events/groups
     * @param int $entityId
     * @param string|null $sort
     * @param int $discussionId
     *
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getDiscussionById(string $type, int $entityId, int $discussionId)
    {
        $getModel = DiscussionTrait::getDiscussionTrait($type);

        return $getModel::withCount('comments as comments_count')
        ->where('entity_id', $entityId)
        ->where('id', $discussionId)
        ->first();
    }
}
