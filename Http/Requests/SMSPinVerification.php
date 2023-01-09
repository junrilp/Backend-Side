<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SMSPinVerification extends FormRequest
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
            'mobile_number'    => 'required|max:14',
            'pin'              => 'required|min:1|max:6',
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function messages()
    {
        return [
            'mobile_number.required'    => 'Please enter a phone number',
            'mobile_number.max'         => 'Phone number :max characters.',
            'pin.required'              => 'Please enter a PIN',
            'pin.min'                   => 'PIN :min characters.',
            'pin.max'                   => 'PIN :max characters.'
        ];
    }
}
