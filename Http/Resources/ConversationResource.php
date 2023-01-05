<?php

namespace App\Http\Resources;

use App\Enums\SuspendedUserDetails;
use App\Enums\TableLookUp;
use App\Enums\ChatFilterType;
use App\Enums\ChatType;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    use ChatBlockedUserTrait;
    /**
     * Use mainly in the inbox section where all chats being render
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $userId = authUser()->id;
        $messageType = ChatType::MESSAGE_ALL;

        if (collect([TableLookUp::PERSONAL_MESSAGE, TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS])->contains($this->table_lookup)) {
            $lastChat = $this->resource->getLastChatByUser($userId ?? $this->sender_id);

            $name = null;
            $type = null;

            if (TableLookUp::PERSONAL_MESSAGE_EVENTS === $this->table_lookup){
                $name = $this->event->title ?? '';
                $type = $this->event->gathering_type ?? '';
                $messageType = $this->chat_type;
            }

            if (TableLookUp::PERSONAL_MESSAGE_GROUPS === $this->table_lookup){
                $name = $this->group->name ?? '';
            }

            $receiverUsername = null;
            if ($this->receiver){
                $receiverUsername = $this->receiver->user_name;
            }

            return [
                "id" => $this->id,
                "table_lookup" => $this->table_lookup,
                "table_id" => $this->table_id ?? 0,
                "name" => $name,
                "gathering_type" => $type,
                "interlocutor" => $this->sender->id != $userId ? (new UserResource2($this->whenLoaded('sender')))->setConversationId($this->id) : (new UserResource2($this->whenLoaded('receiver')))->setConversationId($this->id),
                "last_message_user_id" => $lastChat ? $lastChat->user->id : null,
                "last_message_name" => $lastChat ? $this->getName($lastChat->user) : null,
                "last_message_id" => $lastChat ? $lastChat->id : null,
                "last_message" => $lastChat ? $lastChat->message->content : null,
                "last_message_time" => $lastChat ? $lastChat->created_at : null,
                "avatar_url" => $this->sender->id != $userId
                ? $this->whenLoaded('sender', !$this->sender->isAccountSuspended ? new Media(($this->sender->primaryPhoto)) : SuspendedUserDetails::userPhoto)
                : $this->whenLoaded('receiver', new Media($this->receiver->primaryPhoto ?? null)),
                "avatar" => $this->whenLoaded('receiver.primaryPhoto', new MediaResource($this->media)),
                "url" => $this->sender->id != $userId ? "/{$this->sender->user_name}" : "/{$receiverUsername}",
                "user_profile_id" => $this->sender->id != $userId ? $this->sender->id : $this->receiver->id ?? null,
                "has_message" => $this->has_message,
                "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
                "is_member" => true,
                'session' => [ // determine how many unread count, will be use at the conversation model
                    'id' => $this->id,
                    'open' => false,
                    'unread_count' => $this->chats()->countUnreadMessages(),
                ],
                "is_suspended" => $this->sender->isAccountSuspended,
                "is_notification_muted" => $this->muteNotification($userId),
                "has_left_conversation" => false,
                "chat_message_type" => $messageType
            ];
        }

        // Personal message to all pf users
        if ($this->table_lookup === TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS) {
            $lastChat = $this->resource->getLastChatByUser($userId ?? $this->sender_id);

            $type = null;
            $receiverUsername = null;

            if ($this->receiver){
                $receiverUsername = $this->receiver->user_name;
            }

            return [
                "id" => $this->id,
                "table_lookup" => $this->table_lookup ?? 0,
                "table_id" => $this->table_id ?? 0,
                "name" => "All PF Users",
                "gathering_type" => $type,
                "interlocutor" => $this->sender->id != $userId ? (new UserResource2($this->whenLoaded('sender')))->setConversationId($this->id) : (new UserResource2($this->whenLoaded('receiver')))->setConversationId($this->id),
                "last_message_user_id" => $lastChat ? $lastChat->user->id : null,
                "last_message_name" => $lastChat ? $this->getName($lastChat->user) : null,
                "last_message_id" => $lastChat ? $lastChat->id : null,
                "last_message" => $lastChat ? $lastChat->message->content : null,
                "last_message_time" => $lastChat ? $lastChat->created_at : null,
                "avatar_url" => $this->sender->id != $userId
                ? $this->whenLoaded('sender', !$this->sender->isAccountSuspended ? new Media(($this->sender->primaryPhoto)) : SuspendedUserDetails::userPhoto)
                : $this->whenLoaded('receiver', new Media($this->receiver->primaryPhoto ?? null)),
                "avatar" => $this->whenLoaded('receiver.primaryPhoto', new MediaResource($this->media)),
                "url" => $this->sender->id != $userId ? "/{$this->sender->user_name}" : "/{$receiverUsername}",
                "user_profile_id" => $this->sender->id != $userId ? $this->sender->id : $this->receiver->id ?? null,
                "has_message" => $this->has_message,
                "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
                "is_member" => true,
                'session' => [ // determine how many unread count, will be use at the conversation model
                    'id' => $this->id,
                    'open' => false,
                    'unread_count' => $this->chats()->countUnreadMessages(),
                ],
                "is_suspended" => $this->sender->isAccountSuspended,
                "is_notification_muted" => $this->muteNotification($userId),
                "has_left_conversation" => false,
                "chat_message_type" => $messageType
            ];
        }

        // if record is for event chat
        if (collect([TableLookUp::EVENTS, TableLookUp::EVENT_INQUIRY])->contains($this->table_lookup)) {
            $lastChat = $this->lastChat()->first();
            return [
                'id' => $this->id,
                "table_lookup" => $this->table_lookup ?? 0,
                "table_id" => $this->table_id ?? 0,
                "name" => $this->event->title ?? null,
                "last_message_id" => $lastChat ? $lastChat->id : 0,
                "last_message_time" => $lastChat ? $lastChat->created_at : $this->created_at,
                "last_message" => $lastChat ? $lastChat->message->content : "",
                "avatar_url" => new Media($this->event->primaryPhoto),
                "url" => "/events/{$this->event->slug}",
                "gathering_type" => $this->event->gathering_type,
                "chat_type" => $this->event->live_chat_type,
                "has_message" => $this->has_message,
                "chat_owner" => $this->event->user_id,
                "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
                'session' => [ // determine how many unread count, will be use at the conversation model
                    'id' => $this->id,
                    'open' => false,
                    'unread_count' => $this
                        ->chats()
                        ->whereRaw("(NOT FIND_IN_SET(?, seen_by) OR seen_by IS NULL)", [$userId])
                        ->count(),
                ],
                "is_suspended" => false,
                "is_notification_muted" => $this->muteNotification($userId),
                "gathering_type" => $this->event->gathering_type,
                "has_left_conversation" => false,
                "chat_message_type" => $messageType
            ];
        }

        // if record is for group chat
        if ($this->table_lookup === TableLookUp::GROUPS) {
            $lastChat = $this->lastChat()->first();
            return [
                'id' => $this->id,
                "table_lookup" => $this->table_lookup ?? 0,
                "table_id" => $this->group->conversation->table_id ?? 0,
                "name" => $this->group->name,
                "last_message_id" => $lastChat ? $lastChat->id : 0,
                "last_message_time" => $lastChat ? $lastChat->created_at : $this->created_at,
                "last_message" => $lastChat ? $lastChat->message->content : "",
                "avatar_url" => new Media($this->group->media),
                "url" => "/groups/{$this->group->slug}",
                "has_message" => $this->has_message,
                "chat_owner" => $this->group->user_id,
                "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->group->conversation->id),
                'session' => [ // determine how many unread count, will be use at the conversation model
                    'id' => $this->id,
                    'open' => false,
                    'unread_count' => $this->group
                        ->conversation
                        ->chats()
                        ->whereRaw("(NOT FIND_IN_SET(?, seen_by) OR seen_by IS NULL)", [$userId])
                        ->count(),
                ],
                "is_suspended" => false,
                "is_notification_muted" => $this->muteNotification($userId),
                "has_left_conversation" => false,
                "chat_message_type" => $messageType
            ];
        }

        // if record is for custom group chat
        if ($this->table_lookup === TableLookUp::CUSTOM_GROUP_CHAT) {
            $lastChat = $this->lastChat()->first();
            $hasLeft = $this->hasLeftConversation($userId);

            return [
                'id' => $this->id,
                "table_lookup" => $this->table_lookup ?? 0,
                "table_id" => $this->customGroup->conversation->table_id ?? 0,
                "name" => $this->customGroup->name ?? $this->customGroup->members->pluck('fullName')->take(4)->implode(', '),
                "last_message_id" => $lastChat ? $lastChat->id : 0,
                "last_message_time" => $lastChat ? $lastChat->created_at : $this->created_at,
                "last_message" => $lastChat ? $lastChat->message->content : "",
                "avatar_url" => $lastChat ? new Media($lastChat->user->primaryPhoto) : null,
                "url" => null,
                "has_message" => $this->has_message,
                "chat_owner" => $this->customGroup->user_id,
                "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->customGroup->conversation->id),
                'session' => [ // determine how many unread count, will be use at the conversation model
                    'id' => $this->id,
                    'open' => false,
                    // Check if user has left the conversation then default the unread to 0
                    'unread_count' => $hasLeft ? 0 : $this->customGroup
                        ->conversation
                        ->chats()
                        ->whereRaw("(NOT FIND_IN_SET(?, seen_by) OR seen_by IS NULL)", [$userId])
                        ->count(),
                ],
                "is_suspended" => false,
                "is_notification_muted" => $this->muteNotification($userId),
                "has_left_conversation" => $hasLeft,
                "chat_message_type" => $messageType
            ];
        }
    }

    private function getName($user)
    {
        if ($user->isAccountSuspended) {
            return SuspendedUserDetails::userName;
        }
        return $user->first_name . ' ' . $user->last_name;
    }

    private function primaryPhoto($primaryPhoto)
    {

        if (!$primaryPhoto) {
            return null;
        }

        $locationParts = explode('.', $primaryPhoto->location);
        $extension = array_pop($locationParts);
        if ($primaryPhoto->modification_suffix) {
            $modified = implode('.', $locationParts) . $primaryPhoto->modification_suffix . '.' . $extension;
        }
        return isset($modified) ? getFileUrl($modified) : getFileUrl($primaryPhoto->location);
    }
}
