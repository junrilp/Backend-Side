<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for email
 *
 * @author Junril Pateño <junril090693@gmail.com>
 */
class PasswordChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function rules()
    {
        return [
            'email' => 'required|email'
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
            'email.required' => 'Email is required',
            'email.email' => 'Email must be in a correct format'
        ];
    }
}
