<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\UserDiscussionAttachment;
use App\Models\EventAlbumItem;
use App\Models\GroupAlbumItem;
use App\Models\GroupWallAttachment;
use App\Models\EventWallAttachment;

class MediaInterestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
            $entityType = '';
            $entity = '';

            if ($this->user->relationLoaded('walls')){
                $entityType = 'users';
                $entity = 'user_discussions';
                $images = WallAttachmentResource::collection(UserDiscussionAttachment::whereIn('user_discussion_id',$this->user->walls->pluck('id'))->with('media')->get());
            }

            if ($this->user->relationLoaded('eventWalls')){
                $entityType = 'events';
                $entity = 'event_wall_discussions';
                $images = WallAttachmentResource::collection(EventWallAttachment::whereIn('event_wall_discussion_id',$this->user->eventWalls->pluck('id'))->with('media')->get());
            }

            if ($this->user->relationLoaded('eventAlbums')){
                $entityType = 'events';
                $entity = 'event_albums';

                $images = WallAttachmentResource::collection(EventAlbumItem::whereIn('event_albums_id',$this->user->eventAlbums->pluck('id'))->with('media')->get());
            }

            if ($this->user->relationLoaded('groupWalls')){
                $entityType = 'groups';
                $entity = 'group_wall_discussions';
               $images = WallAttachmentResource::collection(GroupWallAttachment::whereIn('group_wall_discussion_id',$this->user->groupWalls->pluck('id'))->with('media')->get());
            }

            if ($this->user->relationLoaded('groupAlbums')){
                $entityType = 'groups';
                $entity = 'group_albums';
                $images = WallAttachmentResource::collection(GroupAlbumItem::whereIn('group_albums_id',$this->user->groupAlbums->pluck('id'))->with('media')->get());
             }

            $allAttachments =  [
                'entity_type' => $entityType,
                'entity' => $entity,
                'entity_id' => $this->id,
                'user_id' => $this->id,
                'media_ids' => $images->pluck('media_id')
            ];

            return [
                'id' => $this->id,
                'entity_type' => $entityType,
                'entity' => $entity,
                'user' => new UserBasicInfoResource($this->user),
                'attachment' => $images->first(),
                'attachments' => config('app.url') . '/api/all-media-by-interest-attachments/?params=' . json_encode($allAttachments),
                'attachment_body' => $allAttachments
            ];
    }
}
