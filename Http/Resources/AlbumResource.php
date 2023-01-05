<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
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
            'name' => $this->name,
            'total_count' => $this->total_count ?? $this->whenLoaded('visualFiles', COUNT($this->visualFiles)),
            //'visual_files' => $this->whenLoaded('visualFiles', !empty($this->visualFiles) ? MediaResource::collection($this->visualFiles) : ''),
            'created_at' => $this->created_at,
            'updated_at' => date("F j, Y g:i A", strtotime($this->updated_at)),
            'cover' => $this->cover,
            'is_wall' => $this->is_wall,
            'resource_title' => $this->whenLoaded('resourceEntity', $this->resourceEntity->title ?? $this->resourceEntity->name)
        ];
    }
}
