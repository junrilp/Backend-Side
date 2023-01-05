<?php

namespace App\Repository\Notification;

use App\Models\AdminNotification;
use App\Models\AdminNotificationViews;

class NotificationRepository implements NotificationInterface
{
    /**
     * @param int $userId
     * @param int $perPage
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getAdminNotification(int $userId, int $perPage)
    {
        $getView = AdminNotificationViews::where('user_id', $userId)->pluck('admin_notification_id')->toArray();

        return AdminNotification::with('author')->orderBy('created_at', 'DESC')->whereNotIn('id', $getView)->paginate($perPage);
    }

    /**
     * @param array $guidelines
     * @param int $userId
     * @param int|null $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function saveOrUpdateCommunityGuidelines(array $guidelines, int $userId, int $id = null)
    {
        $notif = AdminNotification::firstOrCreate([
            'id' => $id
        ], [
            'user_id' => $userId,
            'message' => isset($guidelines['message']) ? $guidelines['message'] : '',
            'remarks'=> isset($guidelines['remarks']) ? $guidelines['remarks'] : '',
        ]);

        return $notif;
    }

    /**
     * @param int $userId
     * @param int $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function removeAdminNotification(int $userId, int $id)
    {
        return AdminNotification::findOrFail($id)->delete();
    }
    
    /**
     * @param array $notif
     * @param int $id
     * @param int $userId
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function viewNotification(array $notif, int $id, int $userId)
    {
       $notifView = new AdminNotificationViews;
       $notifView->admin_notification_id = $id;
       $notifView->user_id = $userId;
       $notifView->time_elapsed = isset($notif['time_elapsed']) ? $notif['time_elapsed'] : 0;
       return $notifView->save();
    }
}