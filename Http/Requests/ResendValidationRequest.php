<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendValidationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * @author Angelito Tan <angelito.t@ragingriverict.com>
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
            'email'         => 'required|email',
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
            'email.required'         => 'Please enter your Email, this field cannot be blank.',
            'email.email'            => 'Valid email is required.',
        ];
    }
}
