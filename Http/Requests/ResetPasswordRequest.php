<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
/**
 * Request validation for password
 *
 * @author Junril Pateño <junril090693@gmail.com>
 */
class ResetPasswordRequest extends FormRequest
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
            'password'  => 'required|min:6'
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     *
     * @return array
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function messages()
    {
        return [
            'password.required' => 'Password is required',
            'password.min' => 'Password must be 6 character minimum'
        ];
    }
}
