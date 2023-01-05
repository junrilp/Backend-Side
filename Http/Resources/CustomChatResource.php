<?php

namespace App\Http\Resources;

use App\Http\Resources\ChatReplyResource;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\SuspendedUserDetails;

class CustomChatResource extends JsonResource
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
        $userId = $this->current_user_id ?? authUser()->id;

        return [
            "id" => $this->id,
            "session_id" => $this->conversation->id,
            "sender_id" => $this->user->id,
            "sender" => $this->type == 0
            ? $this->whenLoaded('user', $this->getName($this->user))
            : $this->whenLoaded('message', $this->getName($this->getSender())),
            "message" => $this->message['content'],
            "time" => $this->created_at,
            "primaryPhoto" => $this->type == 0
            ? $this->whenLoaded('user', new Media($this->user->primaryPhoto))
            : new Media($this->getSender()->primaryPhoto),
            "collocutor" => $this->type == 1 ? true : false,
            "seen" => $this->read_at != null ? true : false,
            "attachments" => $this->whenLoaded('message', new MediasResource($this->message->media)),
            "replies" => new ChatReplyResource($this->reply),
            "blockedUsers" => $this->getBlockedUsers($this->user->id, $this->conversation->id),
            "is_user_blocked" => $this->isUserBlockedToConversation($this->user->id, $this->conversation->id),
            "url" => $userId == $this->user->id ? "/{$this->getSender()->user_name}" : "/{$this->user->user_name}",
            "is_suspended" => $userId == $this->user->id ? $this->getSender()->isAccountSuspended : $this->user->isAccountSuspended,
            "is_notification_muted" => $this->conversation->muteNotification($userId),
        ];
    }

    /**
     * get firstname and lastname
     *
     */
    private function getName($user)
    {
        // Display default name if user account is suspended
        if ($user->isAccountSuspended) {
            return SuspendedUserDetails::userName;
        }
        return $user->first_name . ' ' . $user->last_name;
    }

    private function primaryPhoto($user)
    {

        if ($user->isAccountSuspended || !$user->primaryPhoto) {
            return SuspendedUserDetails::userPhoto;
        }

        $locationParts = explode('.', $user->primaryPhoto->location);
        $extension = array_pop($locationParts);
        if ($user->primaryPhoto->modification_suffix) {
            $modified = implode('.', $locationParts) . $user->primaryPhoto->modification_suffix . '.' . $extension;
        }
        return isset($modified) ? getFileUrl($modified) : getFileUrl($user->primaryPhoto->location);
    }
}
