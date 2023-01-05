<?php

namespace App\Http\Requests;

use Gate;
use Illuminate\Foundation\Http\FormRequest;


class EventRolesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {

        return Gate::allows('can_edit_event', $this->event) ||  Gate::allows('can_update_roles', $this->event) || $this->event->user_id === authUser()->id;

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'roles.*.user_id' => 'required',
            'roles.*.role_id' => 'required',
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'roles.*.user_id.required' => 'User is required',
            'roles.*.role_id.required' => 'Role is required',
        ];
    }
}
