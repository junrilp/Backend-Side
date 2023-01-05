<?php

namespace App\Repository\Converation;

use App\Models\Conversation;

class ConversationRepository implements ConversationInterface
{
    /**
     * @inheritDoc
     */
    public function getOrCreateForUser(int $userId, int $senderId): Conversation
    {
        $conversation = Conversation::query()->firstOrCreate([
            'receiver_id' => $userId,
            'sender_id' => $senderId,
        ]);
        
        $conversation->unDeletedUser($userId);
        
        return $conversation;
    }
}