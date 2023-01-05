<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BecomePerfectFriendRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules()
    {
        return [
            'about_yourself' => ['required'],
            'relationship_status' => ['required'],
            'education' => ['required'],
            'primary_language' => ['required'],
            'are_you_smoker' => ['required'],
            'are_you_drinker' => ['required'],
            'are_you_in_relationship' => ['required'],
            'do_you_have_children' => ['required'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'about_yourself.required'    => 'Please input about yourself.',
            'relationship_status.required'     => 'Relationship status is required.',
            'education.required'         => 'Education is required.',
            'primary_language.required'    => 'Primary language is required.',
            'are_you_smoker.required'      => 'Are you smoke option is required.',
            'are_you_drinker.required'      => 'Are you drinker option is required.',
            'are_you_in_relationship.required'      => 'Are you in relationshop option is required.',
            'do_you_have_children.required'      => 'Do you have children option is required.',
        ];
    }
}
