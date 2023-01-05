<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Enums\DiscussionType;
use App\Traits\DiscussionTrait;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventWallDiscussionRequest extends FormRequest
{
    use DiscussionTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $isMember = DiscussionTrait::isUserBelongsToEventGroupFriend(DiscussionType::EVENTS, $request->segment(3));

        return auth()->check() || $isMember;
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
