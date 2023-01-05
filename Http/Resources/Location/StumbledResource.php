<?php

namespace App\Http\Resources\Location;

use App\Http\Resources\UserResource2;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class StumbledResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "current_user" => new UserResource2(User::find($this->user_id_1)),
            "stumbled_user" => new UserResource2(User::find($this->user_id_2)),
            "current_user_long" => $this['user_1_longitude'],
            "current_user_lat" => $this['user_1_latitude'],
            "stumbled_user_long" => $this['user_2_longitude'],
            "stumbled_user_lat" => $this['user_2_latitude'],
            "distance" => $this['distance'],
        ];
    }
}
