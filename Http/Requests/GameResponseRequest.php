<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameResponseRequest extends FormRequest
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
            'game_entity_id' => ['required', 'integer'],
            'question_id' => ['required', 'integer'],
            'choices_id' => ['required', 'integer'],
        ];
    }
}
