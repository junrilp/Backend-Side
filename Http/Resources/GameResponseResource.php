<?php

namespace App\Http\Resources;

use App\Models\GameResponse;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class GameResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $user = $this->whenLoaded(
            'participant',
            $this->participant ? new UserResource2($this->participant) : null,
            null
        );

        $responses = GameResponse::where('game_entity_id', $this->game_entity_id)
            ->get();

        $response = [];

        foreach ($responses as $item) {
            $response[] = [
                'question' => new GameQuestionResource($item->question),
                'choice' => new GameChoiceSingleResource($item)
            ];
        }

        return [
            'id' => $this->id,
            'user' => $user,
            'response' => $response,
        ];
    }
}
