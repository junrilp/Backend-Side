<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;

class AuthUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'primary_photo' => new MediaResource($this->primaryPhoto()->first()),
            'account_type' => $this->account_type,
            'status' => $this->status,
            'events' => $this->eventsWithChatEnabled,
            'groups' => $this->groupsWithChatEnabled
        ];
    }
}
