<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Traits\DiscussionTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
class AddCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $url = $request->segment(2);

        $isMember = DiscussionTrait::isUserBelongsToEventGroupFriend($url, $request->segment(3));

        return  auth()->check() || $isMember;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        // Allow to post without comment if attachment found
        if ($this->has('attachments')) {
            if (count($this->post('attachments')) > 0){
                return [];
            }
        }

        return [
            'comment' => ['bail', 'required', function ($attribute, $value, $fail) {
                $restricted = checkWordRestriction($this->post('comment'));
                if ($restricted) {
                    $fail($restricted);
                }
            }],
        ];
    }

    public function messages()
    {
        return [
            'comment.required'         => 'You must enter a comment.',
        ];
    }
}
