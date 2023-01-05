<?php

namespace App\Repository\Reports;

use App\Models\Reports;
use App\Models\ReportAttachment;
use App\Traits\AdminTraits;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReportRepository implements ReportInterface
{
    use AdminTraits;
    /**
     * Get a single report
     *
     * @param int $id
     * @return Reports|Boolean
     */
    public static function getReport(int $id)
    {
        $report = Reports::with(['attachment','reportedAccount','reportedBy'])->where('id', $id);

        if (!$report->exists()) {
            return false;
        }

        return $report->first();
    }

    /**
     * Get reports list
     *
     * @param array|null $types
     * @param int|null $perPage
     *
     * @return LengthAwarePaginator
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getReports(array $types = null, int $perPage = null): LengthAwarePaginator
    {
        $query = Reports::with(['attachment','reportedAccount','reportedBy'])->orderByDesc('created_at');

        if($types) {
            $query = $query->whereIn('type', $types);
        }

        return $query->paginate($perPage);
    }

    /**
     * Save a new report or update an existing one
     *
     * @param string $notes
     * @param int $user_id
     * @param int $reporter_id
     * @param string $type
     * @param array|null $attachments
     * @param int|null $id
     *
     * @return Reports
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function saveOrUpdateReport(string $notes=null, int $userId, int $reporterId, string $type, array $attachments = null, string $resource = null, int $resourceId = null)
    {
        $checkReport = Reports::where('user_id', $userId)
                                ->where('resource', $resource)
                                ->where('resource_id', $resourceId);
        if ($checkReport->count() > 0) {
            $report = $checkReport->first();
        } else {
            $report = new Reports;
        }

        if (! empty($resourceId)) {
            $report->resource = $resource;
            $report->resource_id = $resourceId;
        }

        $report->user_id = $userId;
        $report->notes = $notes;
        $report->reporter_id = $reporterId;
        $report->type = AdminTraits::takeActionType($type);
        $report->save();

        if (isset($attachments)) {
            $checkExist = ReportAttachment::where('reports_id', $report->id);
            if (!$checkExist->exists()) {
                foreach ($attachments as $row) {
                    // will be save in separate table as other attachment
                    ReportAttachment::create([
                        'reports_id' => $report->id,
                        'media_id' => $row
                    ]);
                }
            } else {
                foreach ($attachments as $row) {
                    if (! in_array($row, $checkExist->pluck('media_id')->toArray()) ) {
                        ReportAttachment::create([
                            'reports_id' => $report->id,
                            'media_id' => $row
                        ]);
                    }
                }
            }
        }

        return $report;
    }
    
    /**
     * Remove attachments
     *
     * @param int $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function removeReportAttachment(int $id)
    {
        $deleteAttachment = ReportAttachment::find($id);
        $deleteAttachment->attachment()->delete();
        return $deleteAttachment->delete();
    }
}
