<?php

namespace App\Http\Resources;

use App\Enums\MediaTypes;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatReplyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "replied_to" => [
                'id'     => $this->repliedTo->user->id ?? null,
                'name'   => $this->getName($this->repliedTo->user ?? null),
                'avatar' => $this->repliedTo->user->account_image ?? null,
            ],
            'replied_to_message' => [
                'received' => $this->getMessageReply(),
                'type'     => $this->getMessageType(),
                'id'       => $this->id,
            ],
        ];
    }

    /**
     * Get the reply to the message
     * If user reply on the image, it will show the image and the chat message
     * If user reply to the message, it will show the sender original message along with the reply of the user
     */
    public function getMessageReply()
    {
        if ($this->reply_to_media_id) {
            return getFileUrl($this->repliedToMedia->location);
        }
        return $this->repliedTo->message['content'] ?? null;
    }

    /**
     * Get the type of message user has replied
     *
     */
    public function getMessageType()
    {
        if ($this->reply_to_media_id) {
            return $this->repliedToMedia->media_type_id;
        }
        return MediaTypes::TEXT;
    }

    public function getName($user)
    {
        if ($user) {
            return "{$user->first_name} {$user->last_name}";
        }
        return null;
    }
}
