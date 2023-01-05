<?php

namespace App\Http\Resources;

use App\Forms\MediaForm;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Media;
use App\Http\Resources\MediaResource;

class UserMediaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id"=> $this->id,
            "user_id"=> $this->user_id,
            "image"=> MediaForm::getImageURLById($this->image),
            "media"=> new MediaResource(Media::findOrFail($this->image))
        ];
    }
}
