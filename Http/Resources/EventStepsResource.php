<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\EventSteps;
use App\Models\RoleUser;
use Illuminate\Support\Arr;

class EventStepsResource extends JsonResource
{
    protected $step;

    public function setStep(int $step)
    {
        $this->step = $step;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $admins = $this->roleUser ?? null;

        // Name & Location resource
        $data[EventSteps::NAME_LOCATION] = [
            'name'           => $this->title,
            'setting'        => $this->setting,
            'slug'           => $this->slug,
            'venue' => [
                'name'              => $this->venue_location,
                'street'            => $this->street_address,
                'city'              => $this->city,
                'state'             => $this->state,
                'zip_code'          => $this->zip_code,
                'latitude'          => $this->latitude,
                'longitude'         => $this->longitude
            ],
            'secondary_location'=> $this->secondary_location,
            'rsvp_type'         => $this->rsvp_type,
            'max_capacity'      => $this->max_capacity,
            'live_chat_enabled' => $this->live_chat_enabled,
            'live_chat_type'    => $this->live_chat_type
        ];

        // Type, Category & Interests resource
        $data[EventSteps::TYPE_CATEGORY] = [
            'type'      => $this->type,
            'category'  => $this->category,
            'interests' => InterestResource::collection($this->interests)
        ];

        // Description resource
        $data[EventSteps::DESCRIPTION] = [
            'description' => $this->description
        ];

        // Cover Photo, Video resource
        $data[EventSteps::MEDIA_SETTING] = [
            'image'             => $this->image,
            'video_id'          => $this->video_id,
            'banner_photo'      => new Media($this->primaryPhoto),
            'preview_video'     => new Media($this->video),
        ];

        // Roles and Responsibilities resource
        $data[EventSteps::ROLES] = [
            "owner" => new UserBasicInfoResource2($this->host),
            "roles" => RoleUserResource::collection($admins),
            "is_owner" => authCheck() ? $this->host->id === authUser()->id : false,
            "abilities" => $this->abilities
        ];

        // Date & Time resource
        $data[EventSteps::DATE_TIME] = [
            "event_start"  => $this->event_start,
            "start_time"   => $this->start_time,
            "event_end"    => $this->event_end,
            "end_time"     => $this->end_time,
            "timezone_id"  => $this->timezone_id,
            "timezone"     => new TimezoneResource($this->timezone),
            "is_published" => $this->is_published,
        ];

        if ($this->step !== EventSteps::ALL) {
            return array_merge(['id' => $this->id], $data[$this->step]);
        }

        // Retrieve all the response
        return Arr::collapse(collect($data)->merge([['id' => $this->id]])->toArray());
    }
}
