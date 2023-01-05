<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserBioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'income_level'          => ['bail', 'required'],
            'ethnicity'             => ['bail', 'required'],
            'are_you_smoker'        => ['bail', 'required'],
            'are_you_drinker'       => ['bail', 'required'],
            'relationship_status'   => ['bail', 'required'],
            'any_children'          => ['bail', 'required'],
            'educational_level'     => ['bail', 'required'],
            'city'                  => ['bail', 'required'],
            'state'                 => ['bail', 'required'],
            'zip_code'              => ['bail', 'required'],
            'latitude'              => ['bail', 'required'],
            'longitude'             => ['bail', 'required'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'income_level.required'         => 'Income level is required.',
            'ethnicity.required'            => 'Ethnicity is required.',
            'are_you_smoker.required'       => 'Are you smoker is required.',
            'are_you_drinker.required'      => 'Are you drinker is required.',
            'relationship_status.required'  => 'Relationship status is required.',
            'any_children.required'         => 'Any children is required.',
            'educational_level.required'    => 'Educational level is required.',
            'city.required'                 => 'city is required.',
            'state.required'                => 'state is required.',
            'zip_code.required'             => 'zip_code is required.',
            'latitude.required'             => 'latitude is required.',
            'longitude.required'            => 'longitude is required.',
        ];
    }
}
