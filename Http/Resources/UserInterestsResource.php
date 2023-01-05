<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Interest;
use App\Http\Resources\InterestResource;

class UserInterestsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        return $this->map(function ($value ){
            return [
                "id"=> $value->id,
                "user_id"=> $value->user_id,
                "interest_id"=> $value->interest_id,
                "interest"=> new InterestResource(
                    Interest::where('id', $value->interest_id)->get()->first()
                )
            ];
        });
    }
}
