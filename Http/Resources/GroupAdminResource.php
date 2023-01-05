<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupAdminResource extends JsonResource
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
            $this->merge((new GroupCoreResource($this))),
            'owner' => new UserBasicInfoResource2($this->user),
            'admins' => UserSearchResource::collection($this->admins),
        ];
    }
}
