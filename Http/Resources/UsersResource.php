<?php

namespace App\Http\Resources;

use App\Forms\EnumForms;

use App\Models\Media;
use App\Models\UserInterest;
use App\Models\UserPhoto;
use App\Models\UserPreference;
use App\Models\UserProfile;
use Illuminate\Http\Resources\Json\JsonResource;

class UsersResource extends JsonResource
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
                "user_name"=> $value->user_name ,
                "first_name"=> $value->first_name,
                "last_name"=> $value->last_name,
                "email"=> $value->email ,
                "email_verified_at"=> $value->email_verified_at,
                "media"=> new MediaResource(
                    Media::where('id', $value->image)->get()->first()),
                "gender"=> $value->gender,
                "birth_date"=> $value->birth_date,
                "zodiac_sign"=> EnumForms::getEnum((int)$value->zodiac_sign, 'App\Enums\ZodiacSignType'),
                "account_type"=> $value->account_type,
                "status"=> $value->status,
                "validate_token"=> $value->validate_token,
                "created_at" => $value->created_at,
                "bio" => new UserBioResource(
                    UserProfile::where('user_id', $value->id)->get()->first()),
                "preference" => new UserPreferenceResource(
                    UserPreference::where('user_id', $value->id)->get()->first()),
                "interests"=> new UserInterestsResource(
                    UserInterest::where('user_id', $value->id)->get()),
                "medias"=> new UserMediasResource(
                    UserPhoto::where('user_id', $value->id)->get()),
            ];
        });



    }
}
