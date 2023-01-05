<?php

namespace App\Http\Resources;

use App\Forms\MediaForm;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendChatResource extends JsonResource
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
            "id" => $this->id,
            "name" => $this->first_name . ' ' . $this->last_name,
            "first_name" => $this->first_name,
            "last_name" => $this->last_name,
            "url" => new Media($this->primaryPhoto)
        ];

    }
}
