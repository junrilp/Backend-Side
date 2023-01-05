<?php

namespace App\Http\Requests;

use Gate;
use Illuminate\Foundation\Http\FormRequest;

class TypeCategoryInterestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('can_edit_event', $this->event) || $this->event->user_id === authUser()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'interest' => ['required'],
            'interest.*' => ['required'], // For array that has no value
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'interest.required'             => 'Interest is required.',
            'interest.*.required'             => 'Interest array is required.'
        ];
    }
}
