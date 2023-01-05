<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TagRequest extends FormRequest
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
            'label' => ['bail', 'required', 'min:2', 'max:35', function ($attribute, $value, $fail) {
                            $restricted = checkWordRestriction($this->post('label'));
                            if ($restricted) {
                                $fail($restricted);
                            }
                        }],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'label.required' => 'Tag label is required.',
        ];
    }
}
