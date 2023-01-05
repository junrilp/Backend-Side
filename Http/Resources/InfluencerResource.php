<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\InterestResource;

class InfluencerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     * @author Angelito Tan
     */
    public function toArray($request)
    {
        return [
            "id"          => $this->id,
            "user_name"   => $this->user_name,
            "name"        => $this->fullName,
            "media_path"  => getFullImage($this->primaryPhoto->location ?? ''),
            $this->mergeWhen($request->has('withInterest'),[
                "interests" => InterestResource::collection($this->interests),
            ]),
            "hp_influencer" => $this->hp_influencer,
            "account_type" => $this->account_type
        ];
    }
}
