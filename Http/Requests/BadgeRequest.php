<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BadgeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        switch ($this->method()) {
            case 'POST':
                return true;
                break;
            case 'PATCH':
                // Check if badge owner is same to logged-in user
                return $this->badge->user_id === authUser()->id;
                break;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required','max:100'],
            'description' => ['required','max:255']
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'name.required'         => 'Badge name is required.',
            'name.max'              => 'Badge name can be no longer than :max characters.',
            'description.required'  => 'Badge description is required.',
            'description.max'       => 'Badge description can be no longer than :max characters.'
        ];
    }
}
