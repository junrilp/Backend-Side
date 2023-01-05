<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Traits\DiscussionTrait;
use Illuminate\Foundation\Http\FormRequest;

class DeleteDiscussionRequest extends FormRequest
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

        $getModel = DiscussionTrait::getDiscussionTrait($url);

        return $getModel::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();
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
