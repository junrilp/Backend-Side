<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GroupResourceCollection extends ResourceCollection
{
    protected $authUserId;

    public function setAuthUserId(int $userId)
    {
        $this->authUserId = $userId;
        return $this;
    }
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return $this->collection->map(function (GroupResource $resource) use ($request) {
            return $resource->setAuthUserId($this->authUserId ?? 0)->toArray($request);
        })->all();
    }
}
