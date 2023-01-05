<?php

namespace App\Http\Resources;

use App\Traits\MediaTraits;
use Illuminate\Http\Resources\Json\JsonResource;

class EventAttendeeResource extends JsonResource
{
    use MediaTraits;

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
            "about_me" => $this->profile->about_me ?? '',
            $this->mergeWhen(authCheck(),[
                "is_favorite" => isset($this->is_favorite) ? $this->is_favorite : null,
            ]),
            "what_type_of_friend_are_you_looking_for" => $this->profile->what_type_of_friend_are_you_looking_for ?? '',
            "events_and_activities" => $this->profile->identify_events_activities ?? '',
            "primary_photo"=> new Media($this->primaryPhoto),
            "is_vip" => $this->whenLoaded('userEvent', $this->userEvent ? $this->userEvent->is_vip : false),
            "attended_at" => $this->whenLoaded('userEvent', $this->userEvent ? $this->userEvent->attended_at : null),
            "is_on_queue" => $this->whenLoaded('userEvent', $this->userEvent ? $this->userEvent->is_on_queue : false),
            "is_flagged" => $this->whenLoaded('userEvent', $this->userEvent ? $this->userEvent->owner_flagged : false),
            "remark" => $this->whenLoaded('userEvent', $this->userEvent ? $this->userEvent->flagged_remark : ''),
            "remark_image" => $this->whenLoaded('userEvent', ($this->userEvent && $this->userEvent->media ? $this->primaryPhotoUser($this->userEvent->media): null)),
            "is_ticket_revoked" => $this->whenLoaded('userEvent', $this->userEvent ? $this->userEvent->qr_code === null : false)
        ];

    }
}
