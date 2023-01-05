<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProfileRequest extends FormRequest
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
            'first_name' => 'max:50',
            'last_name' => 'max:50',
            'about_me' => 'max:1000',
            'what_type_of_friend_are_you_looking_for' => 'max:1000',
            'identify_events_activities' => 'max:1000',
        ];
    }

    public function messages()
    {
        return [
            'first_name.max' => 'First name must be less than 50 characters.',
            'last_name.max' => 'Last name must be less than 50 characters.',
            'about_me.max' => 'You are not allowed to post more than 1000 words about yourself.',
            'what_type_of_friend_are_you_looking_for.max' => 'You are not allowed to post more than 1000 words on what type of friends you are looking.',
            'identify_events_activities.max' => 'You are not allowed to post more than 1000 words on your events and activities',
        ];
    }
}
