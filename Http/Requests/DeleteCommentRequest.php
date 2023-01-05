<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Traits\DiscussionTrait;
use Illuminate\Foundation\Http\FormRequest;

class DeleteCommentRequest extends FormRequest
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
        $getModel = DiscussionTrait::getCommentTrait($url);
        
        $ownerModel = DiscussionTrait::getDiscussionTrait($url);

        $isCommentOwner = $getModel::whereId($request->segment(7))->where('user_id', authUser()->id)->exists();
        $isDiscussionTopicOwner = $ownerModel::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();
        $isEventGroupDiscussionOwner = DiscussionTrait::getDiscussionByType($url, $request->segment(3));

        return $isCommentOwner || $isDiscussionTopicOwner || $isEventGroupDiscussionOwner;
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
