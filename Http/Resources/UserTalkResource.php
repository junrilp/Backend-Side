<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class UserTalkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $user = new UserSearchResource(User::find($this->user_id));
        return [
            "id" => $this->id,
            "user" => $user,
            "talk_id" => $this->talk_id,
            "status" => $this->status,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            "can_unmute" => $this->can_unmute,
            "stream_id" => $this->stream_id
        ];
    }
}
