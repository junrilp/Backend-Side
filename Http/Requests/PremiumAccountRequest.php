<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class PremiumAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // bring back later
        // return auth()->check() && auth()->user()->canAccessMessaging();
        return true;
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException('Please upgrade to premium account to send messages.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
