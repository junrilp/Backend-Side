<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules()
    {
        return [
            'user_name'     => ['bail', 'required', 'unique:users', 'max:191'],
            'first_name'    => ['bail', 'required', 'max:191'],
            'last_name'     => ['bail', 'required', 'max:191'],
            'email'         => ['bail', 'required', 'email', 'unique:users', 'max:191'],
            'birth_date'    => ['bail', 'required', 'date_format:"Y-m-d"', 'before:-18 years'],
            // 'password'      => ['bail', 'required', 'confirmed', 'between:6,30'],
            'gender'        => ['bail', 'required'],
            'image'         => ['required'],
            // 'image'         => ['image|mimes:jpeg,png,jpg,gif,svg|dimensions:min_width=400,min_height=400'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'user_name.required'     => 'User name is required.',
            'user_name.unique'       => 'User name is registered.  Please login.',
            'first_name.required'    => 'First name is required.',
            'first_name.max'         => 'First name can be no longer than :max characters.',
            'last_name.required'     => 'Last name is required.',
            'last_name.max'          => 'Last name can be no longer than :max characters.',
            'email.required'         => 'Email is required.',
            'email.email'            => 'Valid email is required.',
            'email.unique'           => 'Email address is registered.  Please login.',
            'email.max:'             => 'Email can be no longer than :max characters.',
            'birth_date.required'    => 'Birth Date is required.',
            'birth_date.date_format' => 'Birth Date was not recognized.  Please try again',
            'birth_date.before'      => 'Must be at least 18 years old to register.',
            'password.required'      => 'Password is required.',
            'password.confirmed'     => 'Password must match both times.',
            'password.between'       => 'Password must be between :min and :max characters.',
            'gender.between'         => 'Gender is required.',
        ];
    }
}
