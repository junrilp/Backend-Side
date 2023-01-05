<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;
use JsonSerializable;

class GameQuestionResource extends JsonResource
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

        $user = $this->whenLoaded(
            'submittedBy',
            $this->submittedBy ? new UserResource2($this->submittedBy) : null,
            null
        );

        $questionType = $this->whenLoaded(
            'type',
            $this->type ? new GameQuestionTypeResource($this->type) : null,
            null
        );

        $media = $this->whenLoaded(
            'media',
            $this->media ? new MediaResource($this->media) : null,
            null
        );

        $choices = $this->whenLoaded(
            'choices',
            $this->choices ? GameChoiceSingleResource::collection($this->choices) : null,
            null
        );

        return [
            'id' => $this->id,
            'value' => $this->value,
            'game_entity' => $gameEntity,
            'user' => $user,
            'question_type' => $questionType,
            'media' => $media,
            'choices' => $choices,
            'status' => $this->status,
        ];
    }
}
