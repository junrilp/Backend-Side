<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource2 extends JsonResource
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
            $this->merge((new GroupNameResource($this))),
            $this->merge((new GroupCategoryResource($this))),
            $this->merge((new GroupDescriptionResource($this))),
            $this->merge((new GroupMediaResource($this))),
            $this->merge((new GroupAdminResource($this)))
        ];
    }
}
