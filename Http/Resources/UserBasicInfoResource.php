<?php

namespace App\Http\Resources;


use App\Http\Resources\Media as MediaResource2;
use App\Models\Media;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class UserBasicInfoResource extends JsonResource
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
            "id" => $this->id,
            "user_name" => $this->user_name,
            "first_name" => $this->first_name,
            "last_name" => $this->last_name,
            "primary_photo"=> new MediaResource2($this->primaryPhoto),
            "primary_photo_url" => getFullImage(Media::whereId($this->image)->first()->location ?? null),
            "primary_photo_modified" => $this->getModifiedMediaUrl(),
            'birth_date' => $this->birth_date,
            "about_me" => $this->profile->about_me ?? null,
            "what_type_of_friend_are_you_looking_for" => $this->profile->what_type_of_friend_are_you_looking_for ?? null,
            "events_and_activities" => $this->profile->identify_events_activities ?? null,
            $this->mergeWhen(authCheck(), [
                "friend_id" => isset($this->friendId) ? $this->friendId->id : null,
                "friendship_status" => $this->friendshipStatusStatic ?? $this->friendshipStatus,
                "is_friend" => isset($this->is_friend) ? $this->is_friend : null,
                "is_favorite" => isset($this->is_favorite) ? $this->is_favorite : null,
            ]),
            "influencer" => $this->account_type == 1 ? 'No' : 'Yes',
            "interests" => $this->whenLoaded('interests', $this->interests->pluck('interest')),
            'account_type' => $this->account_type,
            'status' => $this->status,
            "badges" => $this->whenLoaded('badges', UserBadgeResource::collection($this->badges))
        ];
    }

    /**
     * @return string|null
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    private function getModifiedMediaUrl(): ?string
    {
        $media = $this->whenLoaded('image');
        return $media && !($media instanceof MissingValue) ? optional($media)->getModifiedMediaUrl() : null;
    }
}
