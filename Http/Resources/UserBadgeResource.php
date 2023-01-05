<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserBadgeResource extends JsonResource
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
            "id" => $this->id,
            "name" => $this->name,
            "description" => $this->description,
            "amount" => $this->amount,
            "admin_fee" => $this->admin_fee,
            "badge_type" => $this->badge_type,
            "is_featured" => $this->is_featured,
            "media" => $this->whenLoaded('media', new Media($this->media))
        ];
    }
}
