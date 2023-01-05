<?php

namespace App\Repository\Browse;

interface BrowseInterface
{

    public static function loadUserPreference($request, $userId);

    public static function searchFilter($request);

    public static function elasticSearchBrowse($requestArray, $withLimit, int $perPage, int $limit);

    public static function elasticUserSearch($requestArray, bool $withLimit, int $perPage, int $limit, string $searchMethod, array $friendIds);

    public static function laravelSearch($requestArray, bool $withLimit, int $perPage, int $limit, string $searchMethod, array $friendIds);

}
