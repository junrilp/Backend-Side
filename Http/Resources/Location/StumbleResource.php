<?php

namespace App\Http\Resources\Location;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class StumbleResource extends JsonResource
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
            "user_id" => $this['user']->id,
            "user" => $this['user'],
            "long" => $this['long'],
            "lat" => $this['lat'],
        ];
    }
}
