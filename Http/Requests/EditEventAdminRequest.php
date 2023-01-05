<?php

namespace App\Http\Requests;

use Gate;

class EditEventAdminRequest extends EditEventRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return parent::authorize() &&
            (
            Gate::allows('can_add_gatekeepers', $this->event) ||
            $this->event->user_id === auth()->id()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
