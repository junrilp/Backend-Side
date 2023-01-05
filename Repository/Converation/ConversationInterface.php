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
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function getOrCreateForUser(int $userId, int $senderId): Conversation;
}