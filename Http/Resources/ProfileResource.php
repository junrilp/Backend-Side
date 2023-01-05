<?php

namespace App\Http\Resources;

use App\Traits\MediaTraits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use App\Repository\QrCode\QrCodeRepository;

class ProfileResource extends JsonResource
{
    use MediaTraits;

    protected $fields;

    public function returnFields($requestFields){
        $this->fields = $requestFields;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function toArray($request)
    {

        if (isset($this->fields)) {
            return $this->returnSpecificFields();
        }

        return $this->returnAll();

    }

    /**
     * Return entire model
     * @return array
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function returnAll(){

        return array_merge([
            "user_id"=> $this->id,
            "user_name"=> $this->user_name,
            "first_name"=> $this->first_name,
            "last_name"=> $this->last_name,
            "email"=> $this->email,
            "primary_photo"=> $this->whenLoaded('primaryPhoto', auth()->id()==$this->id ? new Media($this->primaryPhoto) : new Media($this->primaryPhoto)),
            "birth_date"=> $this->birth_date,
            "age" => $this->age,
            "friend_id" => isset($this->friendId) ? $this->friendId->id : null,
            "friendship_status" => $this->friendshipStatus,
            "is_friend" => $this->is_friend,
            "friend_count" => $this->friend_count,
            "is_favorite" => $this->is_favorite,
            "zodiac_sign"=> $this->zodiac_sign,
            "account_type"=> $this->account_type,
            "status"=> $this->status,
            "created_at" => $this->created_at,
            "street_address"=> $this->street_address,
            "interests" => $this->whenLoaded('interests', InterestResource::collection($this->interests)),
            "photos" => $this->whenLoaded('photos', Media::collection($this->photos)),
            "qr_code" => $this->qr_code ? QrCodeRepository::getQrCodeUrl($this->qr_code) : null,
            "total_post" => $this->postCount,
        ], $this->getProfileDetails());
    }

    /**
     * return specific updated fields
     * @return array
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function returnSpecificFields(){

        return [
            $this->checkIfExist("user_name", $this->fields),
            $this->checkIfExist("first_name", $this->fields),
            $this->checkIfExist("last_name", $this->fields),
            $this->checkIfExist("email", $this->fields),
            $this->mergeWhen(in_array("primary_photo", $this->fields), [
                "primary_photo"=> $this->whenLoaded(
                    'primaryPhoto', new Media($this->primaryPhoto)
                ),
            ]),
            $this->checkIfExist("gender", $this->fields),
            $this->checkIfExist("birth_date", $this->fields),
            $this->checkIfExist("age", $this->fields),
            $this->checkIfExist("zodiac_sign", $this->fields),
            $this->checkIfExist("account_type", $this->fields),
            $this->checkIfExist("status", $this->fields),
            $this->checkIfExist("created_at", $this->fields),
            $this->checkIfExist("street_address", $this->fields),
            $this->checkIfExist("city", $this->fields, 'profile'),
            $this->checkIfExist("state", $this->fields, 'profile'),
            $this->checkIfExist("zip_code", $this->fields, 'profile'),
            $this->checkIfExist("country", $this->fields, 'profile'),
            $this->checkIfExist("latitude", $this->fields, 'profile'),
            $this->checkIfExist("longitude", $this->fields, 'profile'),
            $this->checkIfExist("income_level", $this->fields, 'profile'),
            $this->checkIfExist("are_you_smoker", $this->fields, 'profile'),
            $this->checkIfExist("are_you_drinker", $this->fields, 'profile'),
            $this->checkIfExist("any_children", $this->fields, 'profile'),
            $this->checkIfExist("gender", $this->fields, 'profile'),
            $this->checkIfExist("relationship_status", $this->fields, 'profile'),
            $this->checkIfExist("body_type", $this->fields, 'profile'),
            $this->checkIfExist("education_level", $this->fields, 'profile'),
            $this->checkIfExist("ethnicity", $this->fields, 'profile'),
            $this->checkIfExist("interest", $this->fields, 'profile'),
            $this->checkIfExist("what_type_of_friend_are_you_looking_for", $this->fields, 'profile'),
            $this->checkIfExist("about_me", $this->fields, 'profile'),
            $this->checkIfExist("identify_events_activities", $this->fields, 'profile'),
            $this->checkIfExist("height", $this->fields, 'profile'),
            $this->checkIfExist("rate_per_hour", $this->fields, 'profile'),
            $this->mergeWhen(in_array("interests", $this->fields), [
                "interests" => $this->whenLoaded('interests', InterestResource::collection($this->interests)),
            ]),
            $this->mergeWhen(in_array("photos", $this->fields), [
                "photos" => $this->whenLoaded('photos', Media::collection($this->photos))
            ]),
            $this->checkIfExist("total_post", $this->fields),
        ];

    }

    /**
     * @param mixed $fieldname
     * @param mixed $fields
     * @param null $relationship
     *
     * @return \Illuminate\Http\Resources\MergeValue|mixed
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    private function checkIfExist($fieldname, $fields, $relationship = null){ //merge if field isDirty

        if ($relationship==null ) {
            return $this->mergeWhen(in_array($fieldname, $fields), [
                $fieldname => $this->{$fieldname},
            ]);
        }

        return $this->mergeWhen(in_array($fieldname, $fields), [
            $fieldname => $this->whenLoaded($relationship, $this->{$relationship}->{$fieldname} ?? null),
        ]);

    }

    /**
     * @return array
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function getProfileDetails(): array
    {
        $profile = $this->whenLoaded('profile');
        $attributes = [
            'city',
            'state',
            'zip_code',
            'country',
            'latitude',
            'longitude',
            'income_level',
            'are_you_smoker',
            'are_you_drinker',
            'any_children',
            'gender',
            'relationship_status',
            'body_type',
            'education_level',
            'ethnicity',
            'enterests',
            'what_type_of_friend_are_you_looking_for',
            'about_me',
            'identify_events_activities',
            'height',
            'rate_per_hour',
            'badges'
        ];

        return collect(array_flip($attributes))->map(function($index, $key) use ($profile) {
            if ($profile instanceof MissingValue) {
                // Profile is not loaded
                return new MissingValue();
            } else if ($profile instanceof Model) {
                if ($key === 'badges'){
                    return $this->whenLoaded('badges', UserBadgeResource::collection($this->badges));
                }
                return $profile->{$key};
            }
        })->toArray();
    }
}
