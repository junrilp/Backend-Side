<?php

namespace App\Http\Requests;

use App\Enums\DiscussionType;
use App\Models\UserEvent;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

class StoreAlbumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {

        $url = $request->segment(2);
        
        $model = false;

        if ($url === DiscussionType::EVENTS) {
            $model = UserEvent::where('event_id', (int)$request->segment(3));
        }

        if ($url === DiscussionType::GROUPS) {
            $model = UserGroup::where('group_id', (int)$request->segment(3));
        }

        $isMember = $model->where('user_id', authUser()->id)->exists();

        return $isMember;
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
