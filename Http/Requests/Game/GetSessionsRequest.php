<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetSessionsRequest extends FormRequest
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
            'search-type' => ['required', 'string', Rule::in(['all', 'popular', 'my-session'])],
            'include-popular' => ['required', 'integer', Rule::in(['0', '1'])],
            'perPage' => ['nullable', 'integer']
        ];
    }
}
