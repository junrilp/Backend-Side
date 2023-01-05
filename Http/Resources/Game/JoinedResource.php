<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\UserResource2;
use App\Models\Games\WYR\WYR;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class JoinedResource extends JsonResource
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
            'wyr' => new WYRResource([
                'wyr' => $this['wyr'],
                'user' => $this['wyr']->owner,
            ]),
            'joined' => new UserResource2($this['joined']),
        ];
    }
}
