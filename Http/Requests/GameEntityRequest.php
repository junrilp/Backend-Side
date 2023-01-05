<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GameEntityRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'duration' => ['required', 'integer'],
            'game_id' => ['required', 'integer'],
            'host_id' => ['required', 'integer'],
            'game_question_type_id' => ['integer'],
            'game_entity_category_id' => ['required', 'integer'],
        ];
    }
}
