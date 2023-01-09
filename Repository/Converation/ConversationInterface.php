<?php

namespace App\Repository\Converation;

use App\Models\Conversation;

interface ConversationInterface
{
    /**
     * Create conversation for given user
     * @param int $userId
     * @param int $senderId
     * @return Conversation
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getOrCreateForUser(int $userId, int $senderId): Conversation;
}