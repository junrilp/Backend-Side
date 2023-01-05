<?php

namespace App\Http\Requests;

use App\Models\Reports;
use App\Enums\ReportType;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request)
    {
        return  authCheck();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $typesRule = sprintf(
            'in:%s,%s,%s,%s',
            ReportType::SUSPEND,
            ReportType::UNSUSPEND,
            ReportType::INVITE,
            ReportType::UNINVITE);

        return [
            'id' => 'required|number',
            'userId' => 'required',
            'notes' => 'required',
            'type' => ['required', $typesRule],
        ];
    }
}
