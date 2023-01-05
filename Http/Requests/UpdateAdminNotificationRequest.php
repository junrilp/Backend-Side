<?php

namespace App\Http\Requests;

use App\Models\AdminNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateAdminNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $id = $request->segment(4);
        $isExist = AdminNotification::whereId($id)->where('user_id', authUser()->id)->exists();
        return authCheck() && $isExist;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "message" => ["required", Rule::unique('admin_notifications')->ignore($this->id)],
        ];
    }
}
