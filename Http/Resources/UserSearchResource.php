<?php

namespace App\Http\Resources;

use App\Traits\MediaTraits;
use Illuminate\Http\Resources\Json\JsonResource;


class UserSearchResource extends JsonResource
{
    use MediaTraits;
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
            "id"=> $this->id,
            $this->mergeWhen(authCheck(),[
                "friend_id" => isset($this->friendId) ? $this->friendId->id : null,
                "friendship_status" => $this->friendshipStatusStatic ?? $this->friendshipStatus,
                "is_friend" => isset($this->is_friend) ? $this->is_friend : null,
                "is_favorite" => isset($this->is_favorite) ? $this->is_favorite : null,
            ]),
            "about_me" => $this->whenLoaded('profile', $this->profile->about_me ?? ''),
            "what_type_of_friend_are_you_looking_for" => $this->whenLoaded('profile', $this->profile->what_type_of_friend_are_you_looking_for ?? ''),
            "events_and_activities" => $this->whenLoaded('profile', $this->profile->identify_events_activities ?? ''),
            "user_name"=> $this->user_name ,
            "first_name"=> $this->first_name,
            "last_name"=> $this->last_name,
            "influencer"=> $this->account_type==1 ? 'No' : 'Yes',
            "account_type" => $this->account_type,
            "primary_photo"=> $this->when($this->primaryPhoto, new Media($this->primaryPhoto)),
            "interests" => $this->whenLoaded('interests', $this->interests->pluck('interest')),
            "last_login_at" => $this->last_login_at,
            "updated_at" => $this->updated_at,
            "badges" => $this->whenLoaded('badges', UserBadgeResource::collection($this->badges)),
            'role' => $this->whenPivotLoaded('role_user', function () {
                return $this->pivot->role;
            }),
        ];
    }



}
