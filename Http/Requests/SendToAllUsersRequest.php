<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendToAllUsersRequest extends FormRequest
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
            "content" => [function ($attribute, $value, $fail) {
                        $restricted = checkWordRestriction($this->post('content'));
                        if ($restricted) {
                            $fail($restricted);
                        }
                    }],
        ];
    }
}
