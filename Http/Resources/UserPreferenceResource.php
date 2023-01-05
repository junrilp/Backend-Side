<?php

namespace App\Http\Resources;

use App\Forms\EnumForms;
use Illuminate\Http\Resources\Json\JsonResource;

use App\Models\Interest;

class UserPreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        //Convert comma separated field to array
        $interestIds = explode(",",$this->interest_id);

        return [
            "id"=> $this->id,
            "user_id"=> $this->user_id,
            "show_welcome_to_wall"=> $this->show_welcome_to_wall,
            "interests"=> InterestResource::collection(
                Interest::whereIn('id', $interestIds)->get()),
            "income_level"=> EnumForms::getEnums(array_map('intval', explode(',',$this->income_level)), 'App\Enums\IncomeLevelType'),
            "ethnicity"=> EnumForms::getEnums(array_map('intval', explode(',',$this->ethnicity)), 'App\Enums\EthnicityType'),
            "street_address"=> $this->street_address,
            "city"=> $this->city,
            "state"=> $this->state,
            "zip_code"=> $this->zip_code,
            "country"=> $this->country,
            "latitude"=> $this->latitude,
            "longitude"=> $this->longitude,
            "gender"=> EnumForms::getEnums(array_map('intval', explode(',',$this->gender)), 'App\Enums\GenderType'),
            "age_from"=> $this->age_from,
            "age_to"=> $this->age_to,
            "zodiac_sign"=> EnumForms::getEnums(array_map('intval', explode(',',$this->zodiac_sign)), 'App\Enums\ZodiacSignType'),
            "are_you_smoker"=> EnumForms::getEnums(array_map('intval', explode(',',$this->are_you_smoker)), 'App\Enums\SmokingType'),
            "are_you_drinker"=> EnumForms::getEnums(array_map('intval', explode(',',$this->are_you_drinker)), 'App\Enums\DrinkerType'),
            "any_children"=> $this->any_children,
            "education_level"=> EnumForms::getEnums(array_map('intval', explode(',',$this->education_level)), 'App\Enums\EducationalLevel'),
            "relationship_status"=> EnumForms::getEnums(array_map('intval', explode(',',$this->relationship_status)), 'App\Enums\RelationshipStatusType'),
            "body_type"=> EnumForms::getEnums(array_map('intval', explode(',',$this->body_type)), 'App\Enums\BodyType'),
            "height_from"=> $this->height_from,
            "height_to"=> $this->height_to
        ];
    }
}
