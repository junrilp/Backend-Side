<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Forms\MediaForm;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ChatBlockedUserTrait;
class EventConversationResource extends JsonResource
{
    use ChatBlockedUserTrait;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $userId = authUser()->id;

        $userProfile = null;
        if ($this->sender){
            //question why need to compare these id?
            //this is getting an error if the user is about to enter the live chat
            //as a fix need to check the existence of the receiver
            if($this->receiver) {
                $userProfile = $this->sender->id != $userId ? $this->sender->user_name : $this->receiver->user_name;
            } else {
                //default to the sender's data
                $userProfile = $this->sender->user_name;
            }
        }

        return [
            "id" => $this->id,
            "table_lookup" => $this->table_lookup,
            "table_id" => $this->table_id,
            "widget" => "event",
            "name" => $this->event->title,
            "last_message_user_id" => $this->lastChat[0]->user['id'] ?? 0,
            "last_message_name" => $this->getName($this->lastChat[0] ?? null),
            "last_message_id" => $this->lastChat[0]->id ?? 0,
            "last_message" => $this->lastChat[0]->message['content'] ?? null,
            "last_message_time" => $this->lastChat[0]->created_at ?? Carbon::now(),
            "has_message" => $this->has_message,
            "avatar_url" => new Media($this->event->primaryPhoto),
            "url" => "/events/{$this->event->slug}",
            "user_profile_url" => $userProfile,
            "gathering_type" => $this->event->gathering_type,
            "chat_type" => $this->event->live_chat_type,
            "checked_in_users" => $this->event->attendees()->whereNotNull('attended_at')->pluck('user_id'), // This will filter who should only received the chat for the check-ins
            "chat_owner" => $this->event->user_id,
            "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
            "is_member" => in_array($userId, $this->event->attendees()->pluck('user_id')->toArray()),
            "is_notification_muted" => $this->muteNotification($userId),
            'session' => [
                'id' => $this->id,
                'open' => false,
                'unread_count' => $this
                    ->chats()
                    ->where('user_id', '!=', $userId)
                    ->whereRaw("(NOT FIND_IN_SET(?, seen_by) OR seen_by IS NULL)",[$userId])
                    ->count(),
            ],
        ];
    }

    private function getName($user)
    {
        if ($user) {
            return $user['first_name'] . ' ' . $user['last_name'];
        }
    }
}
