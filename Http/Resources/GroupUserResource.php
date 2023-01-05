<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Models\Group;

class GroupUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id"=> $this->id,
            "group_id"=> $this->group_id,
            "group"=> new GroupResource(Group::whereId($this->group_id)->with('user')->first()),
            "user_id"=> $this->user_id,
            "user"=> User::whereId($this->user_id)->first(),
            "created_at"=> $this->created_at,
            "updated_at"=> $this->updated_at
        ];
    }
}
