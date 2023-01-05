<?php

namespace App\Http\Resources;

use App\Http\Resources\Media as MediaResource;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\UserStatus;

class AdminUserSummaryDetailResource extends JsonResource
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
            'user_name' => $this->user_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'primary_photo' => new MediaResource($this->primaryPhoto),
            'email' => $this->email,
            'account_type' => $this->account_type,
            'is_verified' => $this->status !== UserStatus::NOT_VERIFIED,
            'birth_date' => $this->birth_date,
            'role' => $this->adminRoles()->first()->label ?? null,
            'hp_influencer' => $this->hp_influencer,
            'created_at' => $this->created_at
        ];
    }
}
