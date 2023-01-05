<?php

namespace App\Http\Requests;

class GroupSaveRequest extends GroupAuthorizedRequest
{
    /**
     * Get the validation rules that apply to the request.
     * @return array
     */
    public function rules()
    {
        return [
            'name' => array_merge(['bail',  'min:2', 'max:191'], empty($this->group) ? ['required'] : [], [function ($attribute, $value, $fail) {
                $restricted = checkWordRestriction($this->post('name'));
                if ($restricted) {
                    $fail($restricted);
                }
            }]),
            'image' => empty($this->group) ? ['bail', 'required'] : [],
            'description' => array_merge(['bail', 'min:2', 'max:750'], empty($this->group) ? ['required'] : [], [function ($attribute, $value, $fail) {
                            $restricted = checkWordRestriction($this->post('description'));
                            if ($restricted) {
                                $fail($restricted);
                            }
                }]),
            'type_id' => ['nullable','exists:type,id'],
            'category_id' => ['nullable','exists:category,id'],
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'name.required' => 'Group :attribute is required.',
            'image.required' => 'Group :attribute is required.',
            'description.required' => 'Group :attribute is required.',
        ];
    }
}
