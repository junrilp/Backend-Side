<?php

namespace App\Http\Resources;

use App\Http\Resources\ChatReplyResource;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\SuspendedUserDetails;

class ChatResource extends JsonResource
{
    use ChatBlockedUserTrait;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $senderUserName = $this->getSender()->user_name ?? '';
        return [
            "id" => $this->id,
            "session_id" => $this->conversation->id,
            "sender_id" => $this->type == 0
            ? $this->whenLoaded('user', $this->user->id)
            : $this->whenLoaded('message', $this->getSender()->id ?? 0),
            "sender" => $this->type == 0
            ? $this->whenLoaded('user', $this->getName($this->user))
            : $this->whenLoaded('message', $this->getName($this->getSender())),
            "message" => $this->message['content'],
            "time" => $this->created_at,
            "primaryPhoto" => $this->type === 0
            ? $this->whenLoaded('user', new Media($this->user->primaryPhoto))
            : ($this->getSender() ? new Media($this->getSender()->primaryPhoto) : ''),
            "collocutor" => $this->type == 1 ? true : false,
            "seen" => $this->read_at != null ? true : false,
            "attachments" => $this->whenLoaded('message', new MediasResource($this->message->media)),
            "replies" => new ChatReplyResource($this->reply),
            "blockedUsers" => $this->getBlockedUsers($this->user->id, $this->conversation->id),
            "url" => $this->type == 0 ? "/{$this->user->user_name}" : "/{$senderUserName}",
        ];
    }

    private function getSenderMessage($conversation)
    {

        return auth()->id() != $conversation->sender->id
        ? $conversation->sender->primaryPhoto
        : $conversation->receiver->primaryPhoto;

    }

    private function getName($user)
    {
        // Display default name if user account is suspended
        if (!$user){
            return '';
        }
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
