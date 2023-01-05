<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;
use JsonSerializable;

class GameChoiceSingleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $gameEntity = $this->whenLoaded(
            'game',
            $this->game ? new GameEntityResource($this->game) : null,
            null
        );

        $question = $this->whenLoaded(
            'question',
            $this->question ? $this->question : null,
            null
        );

        $media = $this->whenLoaded(
            'media',
            $this->media ? new MediaResource($this->media) : null,
            null
        );

        return [
            'id' => $this->id,
            'value' => $this->value,
            'game_entity' => $gameEntity,
            'user' => $question->submittedBy ? new UserResource2($question->submittedBy) : null,
            'question_type' => $question->type ? new GameQuestionTypeResource($question->type) : null,
            'media' => $media,
        ];
    }
}
