<?php

namespace App\Http\Resources;

use App\Enums\UserStatus;
use App\Http\Resources\AdminUserSummaryDetailResource;
use App\Http\Resources\Media as MediaResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminAdminSummaryDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $createdBy = User::find($this->adminRoles[0]->pivot->created_by);
        return [
            'id' => $this->id,
            'user_name' => $this->user_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'primary_photo' => new MediaResource($this->primaryPhoto),
            'admin_date' => $this->adminRoles[0]->pivot->created_at,
            'role' => $this->adminRoles()->first()->label ?? null,
            'created_by' => new AdminUserSummaryDetailResource($createdBy),
        ];
    }
}
