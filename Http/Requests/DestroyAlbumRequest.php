<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Models\EventAlbum;
use App\Traits\AlbumTraits;
use Illuminate\Foundation\Http\FormRequest;

class DestroyAlbumRequest extends FormRequest
{
    use AlbumTraits;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $url = $request->segment(2);
        
        $getModel = $this->getAlbumTrait($url, (int)$request->segment(3));

        $isOwner = $getModel->where('user_id', authUser()->id)->exists();
        
        return $isOwner;
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
