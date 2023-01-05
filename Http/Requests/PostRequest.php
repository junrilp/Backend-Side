<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostRequest extends FormRequest
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
            'description' => ['bail', 'required', 'min:2'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'description.required'  => 'Post description is required.',
            'description.min'       => 'Please input atleast 2 characters.',
        ];
    }
}
