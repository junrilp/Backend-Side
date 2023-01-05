<?php

namespace App\Http\Resources;

use App\Forms\EnumForms;
use Illuminate\Http\Resources\Json\JsonResource;

class UserBioResource extends JsonResource
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
            "street_address"=> $this->street_address,
            "city"=> $this->city,
            "state"=> $this->state,
            "zip_code"=> $this->zip_code,
            "country"=> $this->country,
            "latitude"=> $this->latitude,
            "longitude"=> $this->longitude,
            "income_level"=> EnumForms::getEnum((int)$this->income_level, 'App\Enums\IncomeLevelType'),
            "are_you_smoker"=> EnumForms::getEnum((int)$this->are_you_smoker, 'App\Enums\SmokingType'),
            "are_you_drinker"=> EnumForms::getEnum((int)$this->are_you_drinker, 'App\Enums\DrinkerType'),
            "any_children"=> EnumForms::getEnum((int)$this->any_children, 'App\Enums\AnyChildrenType'),
            "gender"=> EnumForms::getEnum((int)$this->gender, 'App\Enums\GenderType'),
            "relationship_status"=> EnumForms::getEnum((int)$this->relationship_status, 'App\Enums\RelationshipStatusType'),
            "body_type"=> EnumForms::getEnum((int)$this->body_type, 'App\Enums\BodyType'),
            "education_level"=> EnumForms::getEnum((int)$this->education_level, 'App\Enums\EducationalLevel'),
            "ethnicity"=> EnumForms::getEnum((int)$this->ethnicity, 'App\Enums\EthnicityType'),
            "enterests"=> $this->enterests,
            "what_type_of_friend_are_you_looking_for"=> $this->what_type_of_friend_are_you_looking_for,
            "about_me"=> $this->about_me,
            "identify_events_activities"=> $this->identify_events_activities,
            "height"=> $this->height,
            "rate_per_hour"=> $this->rate_per_hour
        ];
    }
}
