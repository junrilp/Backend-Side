<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\UserResource2;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class UserAnswerResource extends JsonResource
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
            'user_id' => $this->user->id,
            'user' => new UserResource2($this->user),
            'answer' => new AnswerResource($this->answer),
        ];
    }
}
