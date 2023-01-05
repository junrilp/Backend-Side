<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Http\Request;
use App\Models\EventWallDiscussion;
use Illuminate\Foundation\Http\FormRequest;

class DeleteEventWallDiscussionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $isAllowed = EventWallDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();
        $isEventOwner = Event::whereId($request->segment(3))->where('user_id', authUser()->id)->exists();

        return $isAllowed || $isEventOwner;
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
