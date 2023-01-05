<?php

namespace App\Http\Requests;

use App\Enums\ReportType;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Request $request): bool
    {
        return authCheck();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $typesRule = sprintf(
            'in:%s,%s,%s,%s',
            ReportType::SUSPEND,
            ReportType::UNSUSPEND,
            ReportType::INVITE,
            ReportType::UNINVITE);

        return [
            'userId' => 'required',
            'notes' => 'required',
            'type' => ['required', $typesRule],
        ];
    }
}
