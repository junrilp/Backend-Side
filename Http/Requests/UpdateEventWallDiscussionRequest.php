<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Models\EventWallDiscussion;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEventWallDiscussionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $isAllowed = EventWallDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();

        return $isAllowed;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "body" => [function ($attribute, $value, $fail) {
                        $restricted = checkWordRestriction($this->post('body'));
                        if ($restricted) {
                            $fail($restricted);
                        }
                    }],
        ];
    }
}
