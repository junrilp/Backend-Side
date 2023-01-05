<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
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
            'reported_by' => new UserBasicInfoResource2(User::whereId($this->reporter_id)->first()),
            'notes' => $this->note,
            'media' =>  json_decode($this->media_id) ? new MediaResource(Media::whereId(json_decode($this->media_id)[0])->first()) : null,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
