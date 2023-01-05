<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ChatBlockedUserTrait;
use Carbon\Carbon;

class TalkConversationResource extends JsonResource
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

        return [
            "id" => $this->id,
            "table_lookup" => $this->table_lookup,
            "table_id" => $this->table_id,
            "widget" => "talk",
            "name" => $this->talk->title ?? $this->talk->members->pluck('fullName')->take(4)->implode(', '),
            "last_message_user_id" => $this->lastChat[0]->user['id'] ?? 0,
            "last_message_name" => $this->getName($this->lastChat[0] ?? null),
            "last_message_id" => $this->lastChat[0]->id ?? 0,
            "last_message" => $this->lastChat[0]->message['content'] ?? null,
            "last_message_time" => $this->lastChat[0]->created_at ?? Carbon::now(),
            "has_message" => $this->has_message,
            "chat_owner" => $this->talk->owner_id,
            "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
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
