<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactUsRequest extends FormRequest
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
            'name'   => 'required',
            'email'  => 'required|email',
            'message' => 'required|min:10'
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
            'name.required'    => 'Please enter your name, this field cannot be blank.',
            'email.required'   => 'Please enter your e-mail address, this field cannot be blank.',
            'email.email'      => 'Email must be in a correct format',
            'message.required' => 'Please enter your message, this field cannot be blank.',
            'message.min'     => 'Message should have at least :min characters.'
        ];
    }
}
