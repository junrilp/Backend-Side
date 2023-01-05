<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
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
            'wyr_id' => ['required', 'integer', 'exists:App\Models\Games\WYR\WYR,id'],
            'user_id' => ['required', 'integer', 'exists:App\Models\User,id'],
            'answer_id' => ['required', 'integer', 'exists:App\Models\Games\WYR\Answer,id'],
        ];
    }
}
