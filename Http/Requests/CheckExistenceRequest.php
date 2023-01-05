<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckExistenceRequest extends FormRequest
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
            'email' => ['bail', 'required', 'email', 'max:191']
        ];
    }

    public function messages()
    {
        return [
            'email.required'          => 'Email is required.',
            'email.email'             => 'Valid email is required.',
            'email.max:'              => 'Email can be no longer than :max characters.',
        ];
    }
}
