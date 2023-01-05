<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserPreferenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
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
            'city'                  => ['bail', 'required'],
            'state'                 => ['bail', 'required'],
            'zip_code'              => ['bail', 'required'],
            'country'               => ['bail', 'required'],
            'latitude'              => ['bail', 'required'],
            'longitude'             => ['bail', 'required'],
            'interest_id'             => ['bail', 'required'],
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
            'city.required'                 => 'city is required.',
            'state.required'                => 'state is required.',
            'zip_code.required'             => 'zip_code is required.',
            'country.required'              => 'country is required.',
            'latitude.required'             => 'latitude is required.',
            'longitude.required'            => 'longitude is required.',
            'interest_id.required'            => 'Please choose atleast 1 interest.',
        ];
    }
}
