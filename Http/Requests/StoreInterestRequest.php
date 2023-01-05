<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterestRequest extends FormRequest
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
            'interest' => ['required', function ($attribute, $value, $fail) {
                $restricted = checkWordRestriction($this->post('interest'));
                if ($restricted) {
                    $fail($restricted);
                }
                 }],
        ];
    }
}
