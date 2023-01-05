<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Notification;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use App\Enums\TableLookUp;
use App\Http\Resources\Media;
use DB;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    use Notifiable, ApiResponser;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */

    public function index()
    {

        $notifications = auth()->user()->notifications;

        return $this->successResponse($notifications);

    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function unreadNotifications()
    {

        $unreadNotifications = auth()->user()->unreadNotifications;

        return $this->successResponse($unreadNotifications);

    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function markAsRead(Request $request)
    {

        $notification = auth()->user()->unreadNotifications->find($request->notification_id);
        $notification->markAsRead();
        return $this->successResponse([
            'read_at' => $notification->read_at,
        ]);

    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function markAsReadByType(Request $request)
    {

        return auth()->user()->unreadNotifications->where('data.type', $request->type)->markAsRead();

        return $this->successResponse('null');

    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function markAllAsRead()
    {

        auth()->user()->unreadNotifications->markAsRead();

        return $this->successResponse('null');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function show(Notification $notification)
    {

        $notification = auth()->user()->notifications->find($notification);

        return $this->successResponse($notification);

    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function countUnread()
    {

        $friendRequestCount = auth()->user()->unreadNotifications->where('data.type', 'friend_request')->count();
        $acceptedFriendRequest = auth()->user()->unreadNotifications->where('data.type', 'accepted_request')->count();
        $newFriendAdded = auth()->user()->unreadNotifications->where('data.type', 'friend_added')->count();

        $data['friend_request'] = $friendRequestCount;
        $data['accepted_request'] = $acceptedFriendRequest;
        $data['friend_added'] = $newFriendAdded;

        return $this->successResponse($data);

    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function countUnreadByType(Request $request)
    {
        $unreadNotificationsCountByType = auth()->user()->unreadNotifications->where('data.type', $request->type)->count();

        return $this->successResponse($unreadNotificationsCountByType);

    }

    /**
     * Compose the lists of notifications to show
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan
     */
    public function listOfNotifications()
    {
        // For messaging notification
        $messages = $this->getMessagesNotification();
        $messageNotifications = collect($messages)->map(function ($item) {
            $conversation = Conversation::where('table_id', $item->table_id)
                            ->where('table_lookup', $item->table_lookup)
                            ->first();

            if ($conversation) {
                $message = "You have new message in {$item->name}"; // default message
                $photo = json_decode($item->photo, true);

                if ((int) $item->table_lookup === TableLookUp::PERSONAL_MESSAGE || (int) $item->table_lookup === TableLookUp::CUSTOM_GROUP_CHAT){
                    $chatLink = "/inbox/{$conversation->id}";
                }else{
                    $chatLink = "/live-chat/{$conversation->id}";
                }

                // For group custom chat, always get the last avatar
                if ((int) $item->table_lookup === TableLookUp::CUSTOM_GROUP_CHAT) {
                    $photo = authUser()
                        ->notifications()
                        ->addSelect('data->photo as photo')
                        ->where('data->table_id', $item->table_id)
                        ->latest()
                        ->limit(1)
                        ->get();
                    $message = "You have a new group chat message";
                    $photo = $photo[0] ? json_decode($photo[0]['photo'], true) : null;
                }

                return [
                    'id'      => $item->id,
                    'message' => $message,
                    'photo'   => $photo['sizes']['thumbnail'] ?? $item->photo,
                    'link'       => $chatLink,
                    'created_at' => $item->created_at,
                ];
            }
            return [];
        });

        /* For notification by types that has similar results */
        $notificationTypes = collect([
            'new_rsvp_notification',
            'set_vip_notification',
            'remove_vip_notification',
            'checked_in_notification',
            'gathering_huddle'
        ]);

        $notificationTypeResults = $notificationTypes->flatMap(function($type){
            return $this->setMessage(new Collection($this->getNotificationByType($type)), $type);
        })->filter(function($item){ // filter only with values
            return $item;
        });
        $allNotifications = $messageNotifications
                                ->merge($notificationTypeResults)
                                ->sortByDesc('created_at')
                                ->values();
        return $this->successResponse($allNotifications);
    }

    /**
     * This will compose the message for the notification lists
     *
     * @param $items
     * @param $type
     *
     * @return array
     */
    public function setMessage(Collection $items, String $type){
        if (isset($items) && $type === 'new_rsvp_notification'){
            return $items->map(function ($item){
                return [
                    'id'      => $item->id,
                    'message' => "{$item->fullname} RSVP to your event {$item->name}",
                    'photo'   => $item->userPhoto,
                    'link'       => $item->url,
                    'created_at' => $item->created_at,
                ];
            });
        }
        if (isset($items) && $type === 'set_vip_notification'){
            return $items->map(function ($item){
                return [
                    'id'      => $item->id,
                    'message' => "You are now a VIP in event {$item->name}",
                    'photo'   => $item->photo,
                    'link'       => $item->url,
                    'created_at' => $item->created_at,
                ];
            });
        }
        if (isset($items) && $type === 'remove_vip_notification'){
            return $items->map(function ($item){
                return [
                    'id'      => $item->id,
                    'message' => "Your VIP status are removed from the event {$item->name}",
                    'photo'   => $item->photo,
                    'link'       => $item->url,
                    'created_at' => $item->created_at,
                ];
            });
        }
        if (isset($items) && $type === 'checked_in_notification'){
            return $items->map(function ($item){
                return [
                    'id'      => $item->id,
                    'message' => "You are now checked-in to event {$item->name}",
                    'photo'   => $item->photo,
                    'link'       => $item->url,
                    'created_at' => $item->created_at,
                ];
            });
        }
        if (isset($items) && $type === 'gathering_huddle'){
            return $items->map(function ($item){
                $photo = json_decode($item->photo, true);
                return [
                    'id'      => $item->id,
                    'message' => "You are invited to huddle {$item->name}",
                    'photo'   => $photo['sizes']['thumbnail'],
                    'link'       => $item->url,
                    'created_at' => $item->created_at,
                ];
            });
        }

    }

    /**
     * Query for the messages notification, it will only get the message by group
     *
     * @return
     * @author Angelito Tan
     */
    public function getMessagesNotification() {

        $userId = authUser()->id;

        return authUser()
            ->notifications()
            ->addSelect(
                'id',
                'data->name AS name',
                'data->table_id AS table_id',
                'data->table_lookup AS table_lookup',
                'data->fullname AS fullname',
                'data->photo AS photo',
                'data->user_photo AS userPhoto',
                DB::raw('max(created_at) as created_at')
            )
            ->selectRaw('COUNT(notifiable_id) as total_unread')
            ->where('notifiable_id', $userId)
            ->where(function($query){
                $query->where('data->type', 'event_message')
                    ->orWhere('data->type', 'group_message')
                    ->orWhere('data->type', 'custom_group_message');
            })

            ->groupBy('data->table_id')
            ->limit(7)
            ->get();
    }

    /**
     * Get the notification by type
     *
     * @param String $type
     *
     * @return
     * @author Angelito Tan
     */
    public function getNotificationByType(String $type) {
        return authUser()
            ->notifications()
            ->addSelect(
                'id',
                'data->name AS name',
                'data->fullname AS fullname',
                'data->photo AS photo',
                'data->user_photo AS userPhoto',
                'data->url AS url',
                'created_at'
            )
            ->where('data->type', $type)
            ->where('notifiable_id', authUser()->id)
            ->orderBy('created_at')
            ->limit(5)
            ->get();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function destroy()
    {

        $notification = auth()->user()->notifications->delete();

        return $this->successResponse($notification);

    }
}
