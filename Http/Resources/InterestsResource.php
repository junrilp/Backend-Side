<?php

namespace App\Http\Resources;

use App\Enums\InterestType;
use App\Forms\MediaForm;
use Illuminate\Http\Resources\Json\JsonResource;


class InterestsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->map(function ($value ){
            return [
                "id"=> $value->id,
                "interest"=> $value->interest ,
                "slug"=> $value->slug,
                "approved"=> $value->approved,
                "is_featured" => $this->is_featured ?? InterestType::NOT_FEATURED
            ];
        });


    }
}
