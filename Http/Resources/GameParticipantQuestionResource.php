<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;
use JsonSerializable;

class GameParticipantQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
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
            $this->choices ? GameParticipantChoiceResource::collection($this->choices) : null,
            null
        );

        return [
            'id' => $this->id,
            'value' => $this->value,
            'question_type' => $questionType,
            'media' => $media,
            'choices' => $choices,
        ];
    }
}
