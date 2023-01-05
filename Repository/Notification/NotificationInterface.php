<?php

namespace App\Repository\Notification;

interface NotificationInterface
{
    public static function getAdminNotification(int $userId, int $perPage);

    public static function saveOrUpdateCommunityGuidelines(array $guidelines, int $userId, int $id = null);

    public static function removeAdminNotification(int $userId, int $id);

    public static function viewNotification(array $notif, int $id, int $userId);
}
