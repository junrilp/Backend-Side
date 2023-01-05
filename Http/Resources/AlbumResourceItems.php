<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResourceItems extends JsonResource
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
            'id' => $this->id,
            'user' => new UserBasicInfoResource2(User::findOrFail($this->user_id)),
            'visual_files' => $this->whenLoaded('media', !empty($this->media) ? new Media($this->media) : ''),
            'created_at' => $this->created_at,
            'updated_at' => date("F j, Y, g:i A", strtotime($this->updated_at))
        ];
    }
}
