<?php

namespace App\Http\Requests;

use App\Models\EventAlbum;
use App\Traits\AlbumTraits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeleteSingleAlbumAttachmentRequest extends FormRequest
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
        $albumId = $request->segment(5);
        $itemId = $request->segment(7);

        //get the model base on url
        $getModel = $this->getAlbumById($url);
        $getItemsModel = $this->addAlbumItems($url);

        //check if album owner
        $isOwner = $getModel::canDeleteAttachment(authUser()->id, $albumId);
        //check if uploader
        $isUploader = $getItemsModel::canDeleteAttachment(authUser()->id, $albumId, $itemId);

        //allow to delete item if uploader or album owner
        return $isOwner OR $isUploader;

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
