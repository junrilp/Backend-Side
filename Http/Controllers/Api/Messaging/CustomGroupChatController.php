<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Enums\ChatBlockedStatus;
use App\Enums\ChatFilterType;
use App\Events\PrivateChatForCustomGroup;
use App\Events\PrivateChatMessageForCustomGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomChatResource;
use App\Http\Resources\CustomConversationResource;
use App\Models\Chat;
use App\Models\ChatReply;
use App\Models\Conversation;
use App\Notifications\Messaging\CustomGroupMessageNotification;
use App\Traits\ApiResponser;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Resources\UserBasicInfoResource;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CustomGroupChatController extends Controller
{
    use ApiResponser;
    use ChatBlockedUserTrait;
    /**
     * Send a new message to the custom group chat
     *
     * @return $request
     * @author Angelito Tan
     */
    public function send(Request $request, Conversation $conversation)
    {
        $userId = authUser()->id;
        // Check user if blocked from the custom group chat
        if ($this->isUserBlockedToConversation($userId, $conversation->id)) {
            return $this->errorResponse('You are blocked from this group chat', Response::HTTP_FORBIDDEN);
        }

        if ($conversation->hasLeftConversation($userId)){
            return $this->errorResponse('You had left from this conversation', Response::HTTP_FORBIDDEN);
        }

        $attachments = $request->post('attachments', []);

        //if there's an attachment, allow empty message
        $content = $request->post('content');
        if (!$content && $attachments !== '') {
            $content = '';
        }

        $message = $conversation->messages()->create(['content' => $content]); // create the message
        $recipientChatMessage = $message->createForSend($conversation->id); // store the message

        // If reply is found
        if ($request->post('reply_to')) {
            ChatReply::create([
                'chat_id' => $recipientChatMessage->id,
                'reply_to_id' => $request->post('reply_to'),
                'reply_to_media_id' => $request->post('reply_to_media_id'),
            ]);
        }
        $conversation->unDeletedAllUsers();
        $conversation->has_message = true;
        $conversation->save();
        $conversation->touch();

        // Attachments
        $media = collect($attachments)->map(function ($row) {
            return [
                'media_id' => $row,
            ];
        });
        $message->addMedia($media);

        $recipientChatMessage->load([
            'conversation',
            'message.media',
        ]);

        $groupId = $conversation->table_id;
        $mutedUserIds = $recipientChatMessage->conversation->getMutedConversationNotifications()->pluck('user_id');
        $receipients = $recipientChatMessage->conversation->customGroup->members()
            ->whereNotIn('user_id', $mutedUserIds)
            ->where('is_chat_blocked', ChatBlockedStatus::NOT_BLOCKED)
            ->where('has_left', ChatFilterType::STILL_IN_CONVERSATION)
            ->get();

        $receipients->each(function($user) use ($recipientChatMessage) {
            broadcast(new PrivateChatMessageForCustomGroup($recipientChatMessage, $user->id));
        });

        // Send a notification to all event member users, except the user who send the chat message and user not muted
        Notification::send(
            $receipients,
            new CustomGroupMessageNotification($groupId, $recipientChatMessage)
        );

        // Sent a broadcast to the chat box
        // This update the current chat / messages
        broadcast(new PrivateChatForCustomGroup($recipientChatMessage, $conversation));

        $chat = Chat::with('user.primaryPhoto',
            'message.conversation.sender.primaryPhoto',
            'message.conversation.receiver.primaryPhoto',
            'message.media')
            ->find($recipientChatMessage->id);

        return $this->successResponse(new CustomChatResource($chat));
    }

    /**
     * Get conversation
     *
     * @author Angelito Tan
     */
    public function show(Conversation $conversation)
    {
        $conversation->load([
            'lastChat.message',
            'lastChat.user',
            'sender.primaryPhoto',
            'sender.profile',
            'customGroup',
        ]);

        return $this->successResponse(new CustomConversationResource($conversation), null);
    }

    /**
     * Get the previous or history of chat messages for the custom group chats
     * @author Angelito Tan
     */
    public function chats(Conversation $conversation)
    {
        $userId = authUser()->id;
        if (!$this->isUserBlockedToConversation($userId, $conversation->id)) {
            $chats = Chat::query()
                ->has('user')
                ->with([
                    'conversation.customGroup',
                    'user.primaryPhoto',
                    'message.conversation.sender.primaryPhoto',
                    'message.conversation.receiver.primaryPhoto',
                    'message.media',
                    'reply.repliedTo.user',
                    'reply.repliedTo.message',
                ])
                ->where('chat_session_id', $conversation->id)
                ->whereRaw("(NOT FIND_IN_SET(?, deleted_for_user) OR deleted_for_user IS NULL)", [$userId]);

            // If user has left the conversation
            if ($conversation->hasLeftConversation($userId)){
                $chats->whereRaw("FIND_IN_SET(?, seen_by)", [$userId]);
            }

            $chats = $chats->latest()
                ->simplePaginate(10);

            return CustomChatResource::collection($chats);
        }
        return $this->errorResponse('You are blocked from this conversation', Response::HTTP_FORBIDDEN);

    }

    /**
     * Show member lists
     * @author Angelito Tan
     */
    public function members(Request $request, Conversation $conversation){
        $users = $conversation->customGroup->members()->paginate(7);
        return $this->successResponse(
            UserBasicInfoResource::collection($users),
            'Success',
            Response::HTTP_OK,
            true
        );
    }
}
