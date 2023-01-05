<?php

namespace App\Repository\Messaging;

use App\Events\PrivateChatEvent;
use App\Events\PrivateUserChatMessageEvent;
use App\Jobs\SendPersonalMessageEmail;
use App\Models\Chat;
use App\Models\ChatReply;
use App\Models\Conversation;

class MessagingRepository implements MessagingInterface
{
    public static function sendMessage($currentUser, Conversation $conversation, array $form )
    {
        $message = $conversation->messages()->create(['content' => $form['content']]);
        $chat = $message->createForSend($conversation->id);

        $toUser = $conversation->sender_id == authUser()->id ? $conversation->receiver_id : $conversation->sender_id;

        $recipientChatMessage = $message->createForReceive($conversation->id, $toUser);

        // If reply is found
        if (isset($form['reply_to']) && !empty($form['reply_to'])) {
            // create a reply for the send
            ChatReply::create([
                'chat_id' => $chat->id,
                'reply_to_id' => $form['reply_to'],
                'reply_to_media_id' => $form['reply_to_media_id']
            ]);

            // create a reply for the receive
            ChatReply::create([
                'chat_id' => $recipientChatMessage->id,
                'reply_to_id' => $form['reply_to'],
                'reply_to_media_id' => $form['reply_to_media_id']
            ]);
        }

        $conversation->unDeletedAllUsers();
        $conversation->has_message = true;
        $conversation->save();
        $conversation->touch();

        // Attachments
        $media = collect($form['attachments'])->map(function($row){
            return [
                'media_id' => $row['id']
            ];
        });

        $message->addMedia($media);



        $recipientChatMessage->load([
            'conversation',
            'message.media',
        ]);

        // Send a broadcast to the widget
        // For widget purpose only
        // This return a data and will be show as a widget if not yet exist in the 'conversation' store
        broadcast(new PrivateUserChatMessageEvent($currentUser, $recipientChatMessage->user, $recipientChatMessage));

        // Sent a broadcast to the chat box
        // This update the current chat / messages
        broadcast(new PrivateChatEvent($chat, $message->content));

        $chat = Chat::with('user.primaryPhoto',
            'message.conversation.sender.primaryPhoto',
            'message.conversation.receiver.primaryPhoto',
            'message.media')
            ->find($chat->id);

        // Send email to recipient
        if ($recipientChatMessage->user->validTypeAccount) {
            SendPersonalMessageEmail::dispatch($recipientChatMessage)->onQueue('high');
        }

        return $chat;
    }
}