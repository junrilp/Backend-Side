<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WallAttachmentResource extends JsonResource
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
        $entityId = '';
        $entity = '';

        if (isset($this->user_discussion_id)) {
            $entity = 'user_discussion_attachment';
            $entityId = $this->user_discussion_id;
            $entityType = 'users';
        }

        if (isset($this->event_wall_discussion_id)) {
            $entity = 'event_wall_attachment';
            $entityId = $this->event_wall_discussion_id;
            $entityType = 'events';
        }

        if (isset($this->event_albums_id)) {
            $entity = 'event_album_items';
            $entityId = $this->event_albums_id;
            $entityType = 'events';
        }

        if (isset($this->group_wall_discussion_id)) {
            $entity = 'group_wall_attachment';
            $entityId = $this->group_wall_discussion_id;
            $entityType = 'groups';
        }

        if (isset($this->group_albums_id)) {
            $entity = 'group_album_items';
            $entityId = $this->group_albums_id;
            $entityType = 'groups';
        }

        return [
            'id' => $this->id,
            'entity' => $entity,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'media' => new Media($this->whenLoaded('media'))
        ];
    }
}
