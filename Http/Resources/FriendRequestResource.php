<?php

namespace App\Http\Resources;

use App\Traits\MediaTraits;
use Illuminate\Http\Resources\Json\JsonResource;


class FriendRequestResource extends JsonResource
{
    use MediaTraits;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $this->user1->friend_id = $this->id;

        $userResource = new UserSearchResource($this->user1);

        return $userResource;

    }



}
