<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\UserResource2;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class WYRResource extends JsonResource
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
            'id' => $this['wyr']->id,
            'title' => $this['wyr']->title,
            'description' => $this['wyr']->description,
            'duration' => $this['wyr']->duration,
            'host' => new UserResource2($this['user'])
        ];
    }
}
