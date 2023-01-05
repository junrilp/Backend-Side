<?php

namespace App\Http\Resources;

use App\Models\Media;
use App\Http\Resources\Media as MediaResource2;
use Illuminate\Http\Resources\Json\JsonResource;

class UserWithSameInterestResource extends JsonResource
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
            "id" => $this->user->id,
            "user_name" => $this->user->user_name,
            "first_name" => $this->user->first_name,
            "last_name" => $this->user->last_name,
            "primary_photo"=> new MediaResource2($this->user->primaryPhoto),
            "primary_photo_url" => getFullImage(Media::whereId($this->user->image)->first()->location ?? null),
            "about_me" => $this->user->profile->about_me ?? null,
            "what_type_of_friend_are_you_looking_for" => $this->user->profile->what_type_of_friend_are_you_looking_for ?? null,
            "events_and_activities" => $this->user->profile->identify_events_activities ?? null,
            $this->mergeWhen(authCheck(), [
                "friend_id" => isset($this->user->friendId) ? $this->user->friendId->id : null,
                "friendship_status" => $this->user->friendshipStatusStatic ?? $this->user->friendshipStatus,
                "is_friend" => isset($this->user->is_friend) ? $this->user->is_friend : null,
                "is_favorite" => isset($this->user->is_favorite) ? $this->user->is_favorite : null,
            ]),
            "influencer" => $this->user->account_type === 2,
            'account_type' => $this->user->account_type,
            'status' => $this->user->status,
        ];
    }
}
