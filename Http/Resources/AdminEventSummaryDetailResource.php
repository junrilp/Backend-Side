<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\RSVPType;

class AdminEventSummaryDetailResource extends JsonResource
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
            'owner' => new AdminUserSummaryDetailResource($this->eventUser),
            'title' => $this->title,
            'slug' => $this->slug,
            'rsvp_type' => $this->rsvp_type,
            'venue_location' => $this->venue_location,
            'event_start' => $this->event_start,
            'start_time' => $this->start_time,
            'event_end' => $this->event_end,
            'end_time' => $this->end_time,
            'attendees_count' => $this->attendees()->count(),
            'attendees_count_checked_in' => $this->attendees()
                ->whereNotNull('attended_at')
                ->count(),
            'attendees_count_invited' => $this->rsvp_type === RSVPType::VIP  
                ? $this->attendees()
                    ->where('is_vip', 1)
                    ->count() 
                : null,
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
        ];
    }
}
