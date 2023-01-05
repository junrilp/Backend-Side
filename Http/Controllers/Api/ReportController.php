<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Repository\Reports\ReportRepository;
use App\Http\Resources\ReportResource;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Http\Requests\RemoveReportAttachmentRequest;

class ReportController extends Controller
{
    use ApiResponser;

    /**
     * Retrieve a list of reports
     *
     * @param Request $request
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 15;
            $reports = ReportRepository::getReports($request->types, $perPage);
            $result = ReportResource::collection($reports);
            
            return $this->successResponse($result, null, Response::HTTP_OK, true);

        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong. ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Retrieve a single report
     *
     * @param $id
     * @return $result
     */
    public function show($id)
    {
        $result = ReportRepository::getReport($id);

        if ($result == false) {
            return $this->errorResponse('Record did not match in any records.', Response::HTTP_BAD_REQUEST);
        }

        return $this->successResponse(new ReportResource($result));
    }

    /**
     * Create a new report
     *
     * @param StoreReportRequest $request
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(StoreReportRequest $request) {
        $userId = $request->userId;
        $notes = $request->notes;
        $type = $request->type;
        $attachments = $request->attachments;
        $reporterId = authUser()->id;

        try {
            $report = ReportRepository::saveOrUpdateReport($notes, $userId, $reporterId, $type, $attachments);
            $report->load('attachment');
            $report->load('reportedAccount');
            $report->load('reportedBy');

            $result = new ReportResource($report);

            return $this->successResponse($result, null, Response::HTTP_OK);
                
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong. ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update a report
     *
     * @param UpdateReportRequest $request
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateReportRequest $request, int $id) {
        $userId = $request->userId;
        $notes = $request->notes;
        $type = $request->type;
        $attachments = $request->attachments;
        $reporterId = authUser()->id;

        try {
            $report = ReportRepository::saveOrUpdateReport($notes, $userId, $reporterId, $type, $attachments, $id);
            $report->load('attachment');
            $report->load('reportedAccount');
            $report->load('reportedBy');
            
            $result = new ReportResource($report);
            
            return $this->successResponse($result, null, Response::HTTP_OK);
                
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong. ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove attachment from report
     *
     * @param RemoveReportAttachmentRequest $request
     * @param int $id
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function removeReportAttachment(RemoveReportAttachmentRequest $request, int $id)
    {
        try {
            ReportRepository::removeReportAttachment($id);

            return $this->successResponse('Successfully removed');

        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong. ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
