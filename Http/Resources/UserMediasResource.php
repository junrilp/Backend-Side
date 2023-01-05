<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Media;
use App\Http\Resources\MediaResource;

class UserMediasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->map(function ($value){
            return [
                "id"=> $value->id,
                "user_id"=> $value->user_id,
                "location" => $value->location ?? '',
                "media_type_id" => $value->media_type_id ?? '',
                "name"  => $value->name ?? '',
                "image"=> $value->image,
                "media"=> new MediaResource(
                    Media::where('id', $value->image)->get()->first()
                ),
                'fi' =>  getFullImage($value->location) ?? ""
            ];
        });
    }
}
