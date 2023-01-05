<?php

namespace App\Http\Requests;

use App\Models\Plan;
use App\Rules\CreditCard;
use App\Rules\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use phpseclib3\File\ASN1\Maps\CountryName;

class PremiumUpgradeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check('api');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'plan_id' => [
                'required',
                Rule::exists('payment_plans', 'id')->where('status', Plan::STATUS_ACTIVE),
            ],
            'payment_method' => (new PaymentMethod())->required()->exists(),
            'card_number' => [
                'bail',
                'required',
                'numeric',
                (new CreditCard()),
            ],
            'card_expiry' => 'required|regex:/^[0-9]{2}\/[0-9]{4}$/',
            'card_cvv' => 'required|numeric',
            'first_name' => 'required',
            'last_name' => 'required',
            'address_1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'postal_code' => 'required',
            'email' => 'required|email',
            'country' => 'required|min:2|max:3',
        ];
    }

    /**
     * @inheritDoc
     */
    public function messages()
    {
        return [
            'card_expiry.regex' => 'The card expiry format is invalid, format is mm/yyyy eg. 01/2021.',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'card_number' => str_replace(' ', '', $this->input('card_number')),
            'card_expiry' => str_replace(' ', '', $this->input('card_expiry')),
        ]);
    }
}
