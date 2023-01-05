<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

class AdminCheckProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $isExisting = false;

        $email = $request->email;
        $number = Str::replace('-', '', $request->mobile_number);
        
        if (isset($email) or isset($number)) {
            $isExisting =  User::where(function ($query) use ($email, $number) {
                                $query->where('email', $email);
                                $query->orWhere('mobile_number', $number);
                            })
                            ->where('id', '!=', $request->id)
                            ->exists();
        }
        
        return !$isExisting;
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
