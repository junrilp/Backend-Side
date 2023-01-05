<?php

namespace App\Repository\Reports;

interface ReportInterface
{
    public static function getReport(int $id);

    public static function getReports(array $types = null, int $perPage = null);

    public static function saveOrUpdateReport(string $notes, int $userId, int $reporterId, string $type, array $attachments = null, string $resource = null, int $resourceId = null);

    public static function removeReportAttachment(int $id);
}
