<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Models\EventWallAttachment;
use App\Models\EventWallDiscussion;
use Illuminate\Foundation\Http\FormRequest;

class DeleteEventWallAttachmentDiscussionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $hasAccessToAttachment = EventWallAttachment::whereId($request->segment(6))->where('event_wall_discussion_id', $request->segment(5))->exists();
        $hasAccessToEventWallDiscussion = EventWallDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();

        return $hasAccessToAttachment && $hasAccessToEventWallDiscussion;
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
