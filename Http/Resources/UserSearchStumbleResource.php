<?php

namespace App\Http\Resources;

use App\Traits\MediaTraits;
use Illuminate\Http\Resources\Json\JsonResource;


class UserSearchStumbleResource extends JsonResource
{
    use MediaTraits;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function toArray($request)
    {

        return [
            "interests" => $this->whenLoaded('interests', $this->interests->pluck('interest')),
            "badges" => $this->whenLoaded('badges', UserBadgeResource::collection($this->badges))
        ];
    }



}
