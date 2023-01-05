<?php

namespace App\Http\Resources;

use App\Models\GameChoice;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class GameParticipantResource extends JsonResource
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
            'player',
            $this->player ? new UserResource2($this->player) : null,
            null
        );

        $responses = $this->whenLoaded(
            'responses',
            $this->responses ? $this->responses : null,
            null
        );

        $response = [];

        foreach ($responses as $item) {
            $response[] = [
                'question' => new GameParticipantQuestionResource($item->question),
                'choice' => new GameParticipantChoiceResource(GameChoice::find($item->choice_id))
            ];
        }

        return [
            'id' => $this->id,
            'user' => $user,
            'response' => $response,
        ];
    }
}
