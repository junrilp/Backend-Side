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
     * @author Junril Pateño <junril090693@gmail.com>
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
