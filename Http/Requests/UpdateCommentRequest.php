<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Traits\DiscussionTrait;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $url = $request->segment(2);
        
        $getModel = DiscussionTrait::getCommentTrait($url);

        $isCommentOwner = $getModel::whereId($request->segment(7))->where('user_id', authUser()->id)->exists();

        return  authCheck() && $isCommentOwner;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'comment'         => ['bail', 'required'],
        ];
    }

    public function messages()
    {
        return [
            'comment.required'         => 'You must enter a comment.',
        ];
    }
}
