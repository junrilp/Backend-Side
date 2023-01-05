<?php

namespace App\Http\Requests;
use App\Enums\TableLookUp;

class CreateConversationRequest extends PremiumAccountRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        if (collect([TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS])->contains($this->post('table_lookup'))){
            return [];
        }
        return [
            'recipient_id' => 'required|exists:users,id',
        ];
    }
}
