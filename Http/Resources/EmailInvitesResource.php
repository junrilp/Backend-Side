<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailInvitesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->pastEvent->id,
            'title' => $this->pastEvent->title,
            'description' => $this->pastEvent->description,
            'attendees_count' => $this->pastEvent->attendees->count()
        ];
    }
}
