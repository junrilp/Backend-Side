<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EditProfileRequest extends FormRequest
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
            'first_name'    => ['bail', 'required', 'max:191'],
            'last_name'     => ['bail', 'required', 'max:191'],
            'birth_date'    => ['bail', 'required', 'date_format:"Y-m-d"', 'before:-18 years'],
            'ethnicity'     => ['bail', 'required'],
            'zodiac_sign'   => ['bail', 'required'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'first_name.required'    => 'First name is required.',
            'first_name.max'         => 'First name can be no longer than :max characters.',
            'last_name.required'     => 'Last name is required.',
            'last_name.max'          => 'Last name can be no longer than :max characters.',
            'gender.required'        => 'Gender is required.',
        ];
    }
}
