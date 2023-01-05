<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MediasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->map(function ($value ){
            return [
                "id"=> $value->id,
                "user_id"=> $value->user_id,
                "media_type_id"=> $value->media_type_id,
                "location"=> new Media($value),
                "media" => new Media($value),
                "name"=> $value->name
            ];
        });
    }
}
