<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class BasicUserEventResource extends JsonResource
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
        $event = new Event;

        return [
            "id" => $this->id,
            "event_id" => $this->event_id,
            "user_id" => $this->user_id,
            "user" => new UserBasicInfoResource(User::findOrFail($this->user_id)),
            "is_user_interested" => authCheck() ? $event->isUserInterested($this->event_id, authUser()->id) : '',
        ];
    }
}
