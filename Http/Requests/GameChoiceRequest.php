<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameChoiceRequest extends FormRequest
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
            'value' => ['string'],
            'question_id' => ['required', 'integer'],
            'game_entity_id' => ['required', 'integer'],
            'media_id' => ['integer'],
        ];
    }
}
