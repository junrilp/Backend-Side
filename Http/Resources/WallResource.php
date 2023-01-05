<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Media;
use App\Models\EventDiscussion;
use App\Models\EventWallDiscussion;
use App\Models\GroupWallDiscussion;
use App\Models\GroupDiscussion;
use App\Enums\UserDiscussionType;
use Illuminate\Http\Resources\Json\JsonResource;

class WallResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $event = new Event;

        // $hasSameBirthdate = User::find($this->user_id);

        // $isSameBirthdate = false;
        // if (
        //     date('m-d', strtotime(Auth::user()->birth_date)) === date('m-d', strtotime($hasSameBirthdate->birth_date)) &&
        //     auth()->id() !== $this->user_id
        // ) {
        //     $isSameBirthdate = true;
        // }
        $userId = $this->user_id;

        // $isSameZodiac = false;
        // if (
        //     date('m-d', strtotime(Auth::user()->zodiac_sign)) === date('m-d', strtotime($hasSameBirthdate->zodiac_sign)) &&
        //     auth()->id() !== $this->user_id
        // ) {
        //     $isSameZodiac = true;
        // }

        $mediaId = (int)$this->media_id ?? null;

        return [
            'id' => $this->id,
            'body' => $this->body ?? '',
            'description' => $this->description ?? '',
            'type' => $this->type ?? 'wall',
            'media_id' => $mediaId,
            'primary_attachment' => $this->whenLoaded('wallAttachment', $mediaId !== null ? new PostAttachmentResource(Media::find($mediaId)) : ''),
            'user' => new UserBasicInfoResource(User::findOrFail($userId)),
            'other_attachment' => $this->whenLoaded('wallAttachment', !empty($this->wallAttachment) ? PostAttachmentResource::collection($this->wallAttachment) : ''),
            'attachments' => WallAttachmentResource::collection($this->whenLoaded('attachments')),
            'total_people_interested' => $this->people_interested ?? 0,
            'slug' => $this->slug ?? $this->event->slug ?? $this->group->slug ?? '',

            /* The commented part of this code will be uncommented once the feature is available
            'total_likes' => $this->when(
                $this->whenLoaded('total_likes'),
                $this->total_likes
            ) ?? 0,
            "did_you_like" => Auth::check() ? (PostLikeReaction::where('user_id',  auth()->id())->where('post_id', $this->id)->exists() ? true : false) : false,
            "has_interest" => Auth::check() ? (PostInterest::where('user_id',  auth()->id())->where('post_id', $this->id)->exists() ? true : false) : false,
            "comment" => CommentResource::collection(UserDiscussionComment::where('discussion_id', $this->id)->where('parent_id', null)->take(3)->get()),
            */
            "is_user_interested" => authCheck() && $this->checkTypeOnlyEvent($this->type) ? $event->isUserInterested((int)$this->entity_id, authUser()->id) : false,
            // "has_same_birthday" => $isSameBirthdate,
            // "has_same_sodiac_sign" => $isSameZodiac,
            'is_transcoding' => $this->whenLoaded(
                'attachments',
                $this->attachments &&
                    $this->attachments->contains(function ($attachment) {
                        return (int) $attachment->media->processing_status === 1;
                    })
            ),
            "is_owner" => authUser() ? ($this->user_id == authUser()->id ? true : false) : false,
            'is_liked' => $this->alreadyLiked,
            'total_likes' => $this->likesCount,
            "extras" => $this->checkIfNotWall($this->type, $this->entity_id),
            'entity' => $this->when($this->entity, function () {
                if (
                    in_array($this->type, [
                        UserDiscussionType::EVENT_PUBLISHED,
                        UserDiscussionType::EVENT_RSVPD
                    ])
                ) {
                    return new EventResource($this->entity);
                } elseif (
                    in_array($this->type, [
                        UserDiscussionType::GROUP_CREATED,
                        UserDiscussionType::GROUP_JOINED
                    ])
                ) {
                    return new GroupResource($this->entity);
                } elseif (
                    in_array($this->type, [
                        UserDiscussionType::EVENT_DISCUSSION_CREATED,
                        UserDiscussionType::EVENT_DISCUSSION_SHARED,
                        UserDiscussionType::GROUP_DISCUSSION_CREATED,
                        UserDiscussionType::GROUP_DISCUSSION_SHARED
                    ])
                ) {
                    return new DiscussionResource($this->entity);
                } elseif ($this->type === UserDiscussionType::EVENT_WALL_SHARED){
                    return new EventWallResource($this->entity);
                } elseif ($this->type === UserDiscussionType::GROUP_WALL_SHARED){
                    return new GroupWallResource($this->entity);
                }
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function checkTypeOnlyEvent($type)
    {
        if ($type === 'event_rsvpd' || $type === 'event_published' || $type === 'event_discussion_created') {
            return true;
        }

        return false;
    }

    private function checkIfNotWall($type, $entityId)
    {

        if ($type === 'event_rsvpd' || $type === 'event_published') {
            return new EventResource(Event::find($entityId));
        }

        if (in_array($type,['event_discussion_created','event_discussion_shared'])) {
            $eventDiscussion = EventDiscussion::whereId($entityId);
            if ($eventDiscussion->exists()) {
                $entityId = $eventDiscussion->select('entity_id')->first()->entity_id;
                return new EventResource(Event::find($entityId));
            }
        }

        if ($type === 'group_created' || $type === 'group_joined') {
            return new GroupResource(Group::find($entityId));
        }

        if (in_array($type,['group_discussion_created','group_discussion_shared'])) {
            $groupDiscussion = GroupDiscussion::whereId($entityId);
            if ($groupDiscussion->exists()) {
                $entityId = $groupDiscussion->select('entity_id')->first()->entity_id;
                return new GroupResource(Group::find($entityId));
            }
        }

        if ($type === 'event_wall_shared'){
            $eventWall = EventWallDiscussion::find($entityId);
            if ($eventWall){
                return new EventResource($eventWall->event);
            }
        }

        if ($type === 'group_wall_shared'){
            $groupWall = GroupWallDiscussion::find($entityId);
            if ($groupWall){
                return new GroupResource($groupWall->group);
            }
        }

        return '';
    }
}
