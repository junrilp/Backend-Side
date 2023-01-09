<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SmsSendMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (authUser()->id === (int) env('FIRST_FRIEND_ID')) return true;
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'message'    => 'required'
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function messages()
    {
        return [
            'message.required'    => 'Please enter a message'
        ];
    }
}
