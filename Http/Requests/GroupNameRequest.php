<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupNameRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required', 'min:2', 'max:191'],
            'live_chat_enabled' => ['boolean'],
        ];
    }
}
