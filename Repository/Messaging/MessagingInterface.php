<?php

namespace App\Repository\Messaging;

use App\Models\Conversation;

interface MessagingInterface
{
    public static function sendMessage($currentUser, Conversation $conversation, array $form);
}