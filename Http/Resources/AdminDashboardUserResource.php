<?php

namespace App\Http\Resources;

use App\Enums\SuspendedUserDetails;
use App\Forms\EnumForms;
use App\Traits\ChatBlockedUserTrait;
use App\Http\Resources\Media as MediaResource2;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardUserResource extends JsonResource
{
    use ChatBlockedUserTrait;

    protected $conversationId;

    public function setConversationId(int $id){
        $this->conversationId = $id;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $primaryPhoto = $this->whenLoaded(
            'primaryPhoto',
            $this->primaryPhoto ?  new MediaResource2($this->primaryPhoto) : null,
            null
        );

        $primaryPhotoUrl = $this->whenLoaded(
            'primaryPhoto',
            $this->primaryPhoto ? getFullImage($this->primaryPhoto->location) : null,
            null
        );

        $isSharingLocation = $this->whenLoaded(
            'stumble',
            $this->stumble ? true : false,
            false
        );

        return [
            "id" => $this->id,
            "user_name" => $this->user_name,
            "first_name" => $this->first_name,
            "last_name" => $this->last_name,
            "email" => $this->email,
            "mobile_number" => $this->mobile_number,
            "email_verified_at" => $this->email_verified_at,
            "primary_photo" => $primaryPhoto,
            "primary_photo_url" => $primaryPhotoUrl,
            "media" => $this->media,
            "gender" => $this->profile ? EnumForms::getEnum((int)$this->profile->gender, 'App\Enums\GenderType') : '',
            "birth_date" => $this->birth_date,
            "zodiac_sign" => EnumForms::getEnum((int)$this->zodiac_sign, 'App\Enums\ZodiacSignType'),
            "account_type" => $this->account_type,
            "status" => $this->status,
            "validate_token" => $this->validate_token,
            "created_at" => $this->created_at,
            "interests" => $this->whenLoaded('interests', $this->interests->pluck('interest')),
            $this->mergeWhen($this->distance, [
                "distance" => $this->distance,
            ]),
            "profile"   => $this->whenLoaded('profile', new UserBioResource($this->profile)),
            "preference" => $this->whenLoaded('preferences',new UserPreferenceResource($this->preferences)),
            "photos" => $this->whenLoaded('photos', Media::collection($this->photos)),
            "subscription_type" => $this->resource->subscription_type,
            "can_access_messaging" => $this->resource->canAccessMessaging(),
            "settings" => $this->whenLoaded('userSettings',new UserSettingsResource($this->userSettings)),
            "is_suspended" => $this->isAccountSuspended,
            "deleted_at" => $this->deleted_at,
            "report" => $this->whenLoaded('reports', ReportResource::collection($this->reports)),
            "is_chat_blocked" => $this->conversationId ? $this->isUserBlockedToConversation($this->id, $this->conversationId) : false,
            "is_favorite" => $this->is_favorite,
            "badges" => $this->whenLoaded('badges', UserBadgeResource::collection($this->badges)),
            "user_notes" => $this->whenLoaded('userNotes', NoteResource::collection($this->userNotes)),
            "last_login_at" => $this->last_login_at,
            "isSharingLocation" => $isSharingLocation,
            'role' => $this->adminRoles()->first()->label ?? null
        ];
    }
}
