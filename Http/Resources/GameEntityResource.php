<?php

namespace App\Http\Resources;

use App\Models\GameEntityCategory;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class GameEntityResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration,
            'game' => new GameResource($this->game),
            'owner' => new UserResource2($this->owner),
            'category' => new GameEntityCategoryResource($this->category),
            'total_questions' => $this->questions->count(),
        ];
    }
}
