<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\NotRegistered;
use App\Enums\UserStatus;

class RegistrationRequest extends FormRequest
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
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function rules()
    {
        return [
            'first_name'    => 'required|max:191',
            'last_name'     => 'required|max:191',
            'email'         => ['required', 'email', 'max:191', new NotRegistered([
                UserStatus::NOT_VERIFIED => 'You have already started the registration process, please click the resend activation link button on this page to continue.',
                UserStatus::VERIFIED => 'This account is already registered, please login to continue. If you forgot your password please click the forgot password link',
                UserStatus::PUBLISHED => 'This email is already registered.',
            ])],
            'birth_date'    => 'required|date_format:Y-m-d|before:18 years ago|after:-115 years',
            'zodiac_sign'   => 'required',
            'image'         => 'required',
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function messages()
    {
        return [
            'first_name.required'    => 'Please enter your First name, this field cannot be blank.',
            'first_name.max'         => 'First name can be no longer than :max characters.',
            'last_name.required'     => 'Please enter your Last name, this field cannot be blank.',
            'last_name.max'          => 'Last name can be no longer than :max characters.',
            'mobile_number.required' => 'Please enter a mobile number, this field cannot be blank.',
            'mobile_number.max'      => 'Mobile number can be no longer than :max characters.',
            'email.required'         => 'Please enter your Email, this field cannot be blank.',
            'email.email'            => 'Valid email is required.',
            'email.unverified'       => 'You have already started the registration process, please click the resend activation button.',
            'email.verified'         => 'This account is already registered, please login to continue. If you forgot your password please click the forgot password link',
            'email.max'              => 'Email can be no longer than :max characters.',
            'birth_date.required'    => 'Please enter your birthday, this field cannot be blank.',
            'birth_date.date_format' => 'Birth Date was not recognized. Please try again',
            'birth_date.before'      => 'Must be at least 18 years old to register.',
            'birth_date.after'       => 'Age must be lower than 115 years old.',
            'image.required'         => 'Please upload a photo, this field cannot be blank.',
        ];
    }
}
