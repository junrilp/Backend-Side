<?php

namespace App\Http\Requests;

use App\Models\UserDiscussionAttachment;
use App\Models\UserDiscussion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class DeleteUserDiscussionAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $hasAccessToAttachment = UserDiscussionAttachment::whereId($request->segment(6))->where('user_discussions_id', $request->segment(5))->exists();
        $hasAccessToUserDiscussion = UserDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();

        return $hasAccessToAttachment || $hasAccessToUserDiscussion;
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
