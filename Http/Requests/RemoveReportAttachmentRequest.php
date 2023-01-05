<?php

namespace App\Http\Requests;

use Illuminate\Http\Request;
use App\Models\ReportAttachment;
use Illuminate\Foundation\Http\FormRequest;

class RemoveReportAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        $id = $request->segment(5);

        return ReportAttachment::whereId($id)->exists();
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
