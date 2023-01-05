<?php

namespace App\Http\Requests;

use App\Enums\DiscussionType;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Traits\DiscussionTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Response;

class StoreDiscussionRequest extends FormRequest
{
    use DiscussionTrait, ApiResponser;
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $url = $request->segment(2);
        
        $isMember = DiscussionTrait::isUserBelongsToEventGroupFriend($url, $request->segment(3));

        return auth()->check() || $isMember;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(Request $request)
    {
        return [
            "title" => ["required", function ($attribute, $value, $fail) {
                $restricted = checkWordRestriction($this->post('title'));
                if ($restricted) {
                    $fail($restricted);
                }
            }],
            "discussion" => ["required", function ($attribute, $value, $fail) {
                $restricted = checkWordRestriction($this->post('discussion'));
                if ($restricted) {
                    $fail($restricted);
                }
            }]
        ];
    }

    
}
