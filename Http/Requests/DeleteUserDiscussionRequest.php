<?php

namespace App\Http\Requests;

use App\Models\UserDiscussion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class DeleteUserDiscussionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $hasAccessToUserDiscussion = UserDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();
       
        return $hasAccessToUserDiscussion;
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
