<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Models\EventWallDiscussionLike;
use Illuminate\Foundation\Http\FormRequest;

class DeleteLikeEventWallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
       return EventWallDiscussionLike::whereId($request->segment(7))->where('user_id', authUser()->id)->exists();
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
