<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;
use JsonSerializable;

class GameParticipantChoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $media = $this->whenLoaded(
            'media',
            $this->media ? new MediaResource($this->media) : null,
            null
        );

        return [
            'id' => $this->id,
            'value' => $this->value,
            'media' => $media,
        ];
    }
}
