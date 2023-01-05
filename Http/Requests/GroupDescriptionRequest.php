<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupDescriptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'description' => ['required']
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'description.required'  => 'Group description is required.',
            'description.max'       => 'The description has a maximum character limitation of :max characters.',
        ];
    }
}
