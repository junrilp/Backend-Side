<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\ChatBlockedUserTrait;
use App\Forms\MediaForm;
use Carbon\Carbon;

class CustomConversationResource extends JsonResource
{
    use ChatBlockedUserTrait;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function toArray($request)
    {
        $userId = authUser()->id;
        $lastChat = count($this->lastChat) ? $this->lastChat[0] : null;

        return [
            "id" => $this->id,
            "table_lookup" => $this->table_lookup,
            "table_id" => $this->table_id,
            "widget" => "custom_group",
            "name" => $this->customGroup->name ?? $this->customGroup->members->pluck('fullName')->take(4)->implode(', '),
            "last_message_user_id" => $this->lastChat[0]->user['id'] ?? 0,
            "last_message_name" => $this->getName($this->lastChat[0] ?? null),
            "last_message_id" => $this->lastChat[0]->id ?? 0,
            "last_message" => $this->lastChat[0]->message['content'] ?? null,
            "last_message_time" => $this->lastChat[0]->created_at ?? Carbon::now(),
            "has_message" => $this->has_message,
            "avatar_url" => $lastChat ? new Media($lastChat->user->primaryPhoto) : null,
            "url" => null,
            "user_profile_url" => null,
            "chat_owner" => $this->customGroup->user_id,
            "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
            //"is_member" => in_array($userId, $this->customGroup->attendees()->pluck('user_id')->toArray()),
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
