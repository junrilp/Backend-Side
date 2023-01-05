<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use App\Forms\MediaForm;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class GroupConversationResource extends JsonResource
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
        $userProfileId = null;
        if ($this->sender){

            //question why need to compare these id?
            //this is getting an error if the user is about to enter the live chat
            //as a fix need to check the existence of the receiver
            if($this->receiver) {
                $userProfile = $this->sender->id != $userId ? $this->sender->user_name : $this->receiver->user_name;
                $userProfileId = $this->sender->id != $userId ? $this->sender->id : $this->receiver->id;
            } else {
                //default to the sender's data
                $userProfile = $this->sender->user_name;
                $userProfileId = $this->sender->id;
            }
        }

        return [
            "id" => $this->id,
            "table_lookup" => $this->table_lookup,
            "table_id" => $this->table_id,
            "widget" => "group",
            "name" => $this->group->name,
            "last_message_user_id" => $this->lastChat[0]->user['id'] ?? 0,
            "last_message_name" => $this->getName($this->lastChat[0] ?? null),
            "last_message_id" => $this->lastChat[0]->id ?? 0,
            "last_message" => $this->lastChat[0]->message['content'] ?? null,
            "last_message_time" => $this->lastChat[0]->created_at ?? Carbon::now(),
            "has_message" => $this->has_message,
            "avatar_url" => new Media($this->group->media),
            "url" => "/groups/{$this->group->slug}",
            "group_id" => $this->group->id,
            "user_profile_url" => $userProfile,
            "user_profile_id" => $userProfileId,
            "chat_owner" => $this->group->user_id,
            "is_user_blocked_to_chat" => $this->isUserBlockedToConversation($userId, $this->id),
            "is_member" => in_array($userId, $this->group->members()->pluck('user_id')->toArray()),
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
