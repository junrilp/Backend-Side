<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
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
            "id"=> $this->id,
            "user_id"=> $this->user_id,
            "media_type_id"=> $this->media_type_id,
            "location"=> getFileUrl($this->location),
            "name"=> $this->name
        ];
    }
}
