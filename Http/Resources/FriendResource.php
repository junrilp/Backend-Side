<?php

namespace App\Http\Resources;

use App\Traits\MediaTraits;
use Exception;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class FriendResource extends JsonResource
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
        //this needs more investigation on this but for now, there's a need to catch the exception here
        try {
            $this->user1->friend_id = $this->id;
            $this->user2->friend_id = $this->id;
        } catch (\Throwable $th) {
            //throw $th;
            Log::error($th);
        }
        

        if ($this->initiated == 0) {
            $userResource = new UserSearchResource($this->user1);
        } else {
            $userResource = new UserSearchResource($this->user2);
        }

        return $userResource;

    }



}
