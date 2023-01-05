<?php

namespace App\Http\Resources;

use App\Http\Resources\Media as MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBasicInfoResource2 extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id"=> $this->id,
            "user_name"=> $this->user_name,
            "first_name"=> $this->first_name,
            "last_name"=> $this->last_name,
            "primary_photo"=> new MediaResource($this->primaryPhoto)
        ];
    }
}
