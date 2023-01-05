<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\RoleUser;
use App\Models\Group;
use Log;

class GroupMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $roleUserQueryBuilder = RoleUser::where('user_id', $this->id)
            ->where('resource', Group::class)
            ->where('resource_id', $this->group_id);
                    
        return [
            $this->merge((new UserBasicInfoResource($this))),
            'role' => $this->when($roleUserQueryBuilder->exists(), function () use ($roleUserQueryBuilder) {
                return $roleUserQueryBuilder->with('role')->first()->role;
            }),
        ];
    }
}
