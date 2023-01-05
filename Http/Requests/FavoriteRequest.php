<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Auth\Access\AuthorizationException;

class FavoriteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return authCheck() && authUser()->id !== $this->to_user_id;
    }

    /**
     * Custom exception when authorization fails
     * 
     * @return AuthorizationException
     */
    protected function failedAuthorization()
    {
        throw new AuthorizationException('It is forbidden to favorite yourself.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}
