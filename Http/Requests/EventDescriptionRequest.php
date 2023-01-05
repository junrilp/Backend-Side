<?php

namespace App\Http\Requests;

use Gate;
use Illuminate\Foundation\Http\FormRequest;

class EventDescriptionRequest extends FormRequest
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
            'description' => ['required', function ($attribute, $value, $fail) {
                                $restricted = checkWordRestriction($this->post('description'));
                                if ($restricted) {
                                    $fail($restricted.'|Restricted Word');
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
            'description.required'  => 'Event description is required.',
            'description.max'       => 'The description has a maximum character limitation of :max characters',
        ];
    }
}
