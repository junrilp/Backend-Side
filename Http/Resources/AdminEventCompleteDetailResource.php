<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;
use App\Repository\QrCode\QrCodeRepository;
use App\Models\Media;
use App\Enums\RSVPType;
use App\Forms\EnumForms;

class AdminEventCompleteDetailResource extends JsonResource
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
            'image_id' => new MediaResource(Media::find($this->image)),
            'description' => $this->description,
            'type' => $this->eventType ? new TypeResource($this->eventType) : null,
            'category' => $this->eventCategory ? new CategoryResource($this->eventCategory) : null,
            'rsvp_type' => $this->rsvp_type,
            'max_capacity' => $this->max_capacity,
            $this->mergeWhen($this->rsvp_type === RSVPType::LIMITED, [
                'capacity' => $this->when(
                    $this->resource->relationLoaded('attendees'),
                    $this->attendees->count() > $this->max_capacity ? 'full' : 'open'
                )
            ]),
            'venue_type' => $this->show_at,
            'venue_location' => $this->venue_location,
            'secondary_location' => $this->secondary_location,
            'event_start' => $this->event_start,
            'start_time' => $this->start_time,
            'event_end' => $this->event_end,
            'end_time' => $this->end_time,
            'timezone' => EnumForms::getEnum((int)$this->timezone_id, 'App\Enums\TimeZone'),
            'street_address' => $this->street_address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'attendees_count' => $this->when(
                $this->resource->relationLoaded('attendees'),
                $this->attendees->count()
            ),
            'attendees_count_checked_in' => $this->attendees()
                ->whereNotNull('attended_at')
                ->count(),
            'attendees_count_invited' => $this->rsvp_type === RSVPType::VIP
                ? $this->attendees()
                    ->where('is_vip', 1)
                    ->count()
                : null,
            'tags' => $this->when(
                $this->resource->relationLoaded('eventTags'),
                TagResource::collection($this->eventTags)
            ),
            'is_featured' => $this->is_feature ?? 0,
            'is_published' => $this->is_published,
            'qr_code' => $this->qr_code ? QrCodeRepository::getQrCodeUrl($this->qr_code) : null,
        ];
    }
}
