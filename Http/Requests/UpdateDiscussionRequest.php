<?php

namespace App\Http\Requests;

use App\Enums\DiscussionType;
use Illuminate\Http\Request;
use App\Traits\DiscussionTrait;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDiscussionRequest extends FormRequest
{
    use DiscussionTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $url = $request->segment(2);
        
        $ownerModel = DiscussionTrait::getDiscussionTrait($url);
       
        $isDiscussionTopicOwner = $ownerModel::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();

        return  authCheck() && $isDiscussionTopicOwner;
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
