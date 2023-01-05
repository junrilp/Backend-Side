<?php

namespace App\Http\Requests;

use App\Models\UserDiscussion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class WallUpdatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $isAllowed = UserDiscussion::whereId($request->segment(5))->where('user_id', authUser()->id)->exists();

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
            'body' => [function ($attribute, $value, $fail) {
                        $restricted = checkWordRestriction($this->post('body'));
                        if ($restricted) {
                            $fail($restricted);
                        }
                    }],
            'video' => 'max:10240'
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'video.max' => 'Maximum file must not more than 10MB'
        ];
    }
}
