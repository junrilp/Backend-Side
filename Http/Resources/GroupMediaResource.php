<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupMediaResource extends JsonResource
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
            $this->merge((new GroupCoreResource($this))),
            'image_id' => $this->image_id,
            "image" => new Media($this->media),
            'video_id' => $this->video_id,
            "video" => new Media($this->video),
        ];
    }
}
