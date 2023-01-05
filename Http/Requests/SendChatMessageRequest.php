<?php

namespace App\Http\Requests;

class SendChatMessageRequest extends PremiumAccountRequest
{
    private $minContentLength = 1;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'content' => "required|string|min:{$this->minContentLength}",
        ];
    }

    /**
     * @return string[]
     */
    public function messages()
    {
        return [
            'content.required' => 'Message is required.',
            'content.min' => "Your message must be at least {$this->minContentLength} characters.",
        ];
    }
}
