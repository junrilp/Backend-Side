<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class GroupNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => Arr::get($this->data, 'title'),
            'message' => Arr::get($this->data,'message'),
            'group' => array_merge(Arr::get($this->data, 'group'), [
                'image' => Arr::get($this->data, 'image'),
                'image_modified' => Arr::get($this->data, 'image_modified'),
            ]),
        ];
    }
}
