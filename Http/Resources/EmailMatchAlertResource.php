<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmailMatchAlertResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $primaryPhotoUrl = $this->whenLoaded(
            'primaryPhoto',
            $this->primaryPhoto ? getFullImage($this->primaryPhoto->location) : null,
            null
        );

        $count = 0; // default value
        if ($this->zodiacMatchCount){
            $count = $this->zodiacMatchCount;
        }

        if ($this->birthdayMatchCount){
            $count = $this->birthdayMatchCount;
        }

        return [
            'id'       => $this->id,
            'name'     => "{$this->first_name} {$this->last_name}",
            'count'    => $count,
            'profile'  => url("/{$this->user_name}"),
            'photo'    => $primaryPhotoUrl,
            'interest' => $this->interests->take(2)
        ];
    }
}
