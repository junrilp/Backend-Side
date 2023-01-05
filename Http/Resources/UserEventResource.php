<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UserEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "event" => new EventResource(Event::findOrFail($this->event_id)),
            "user"=> User::whereId($this->user_id)->first(),
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];
    }
}
