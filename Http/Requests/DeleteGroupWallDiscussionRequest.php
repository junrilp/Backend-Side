<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Http\Request;
use App\Models\GroupWallDiscussion;
use Illuminate\Foundation\Http\FormRequest;

class DeleteGroupWallDiscussionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $isAllowed = GroupWallDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();
        $isEventOwner = Group::whereId($request->segment(3))->where('user_id', authUser()->id)->exists();

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
