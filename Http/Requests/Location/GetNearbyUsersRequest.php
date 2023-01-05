<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class GetNearbyUsersRequest extends FormRequest
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
            'lat' => 'required|numeric|between:-90,90',
            'long' => 'required|numeric|between:-180,180',
            'distance' => 'required|integer|min:1',
            'limit' => 'nullable|integer|min:1',
            'keyword' => 'nullable|string'
        ];
    }
}
