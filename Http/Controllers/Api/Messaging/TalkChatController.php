<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Models\Chat;
use App\Models\Talk;
use App\Models\ChatReply;
use App\Models\Conversation;
use App\Traits\ApiResponser;
use App\Enums\ChatFilterType;
use App\Enums\ChatBlockedStatus;
use App\Http\Requests\ChatRequest;
use App\Events\PrivateChatForTalk;
use App\Traits\ChatBlockedUserTrait;
use App\Http\Controllers\Controller;
use App\Events\PrivateChatMessageForTalk;
use App\Http\Resources\CustomChatResource;
use App\Http\Resources\TalkConversationResource;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class TalkChatController extends Controller
{

    use ApiResponser;
    use ChatBlockedUserTrait;

    /**
     * Send message
     *
     * @return $request
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function send(ChatRequest $request, Conversation $conversation)
    {
        $userId = authUser()->id;

        // Check user if blocked from the talk chat
        if ($this->isUserBlockedToConversation($userId, $conversation->id)) {
            return $this->errorResponse('You are blocked from this group chat', Response::HTTP_FORBIDDEN);
        }

        if ($conversation->hasLeftToTalkConversation($userId)){
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

        $mutedUserIds = $recipientChatMessage->conversation->getMutedConversationNotifications()->pluck('user_id');
        $receipients = $recipientChatMessage->conversation->talk->members()
            ->whereNotIn('user_id', $mutedUserIds)
            ->where('is_chat_blocked', ChatBlockedStatus::NOT_BLOCKED)
            ->where('has_left', ChatFilterType::STILL_IN_CONVERSATION)
            ->get()
            ->merge([$recipientChatMessage->conversation->talk->owner]);

        $receipients->each(function($user) use ($recipientChatMessage) {
            broadcast(new PrivateChatMessageForTalk($recipientChatMessage, $user->id));
        });

        // Sent a broadcast to the chat box
        // This update the current chat / messages
        broadcast(new PrivateChatForTalk($recipientChatMessage, $conversation));

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
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function show(Conversation $conversation)
    {
        $conversation->load([
            'lastChat.message',
            'lastChat.user',
            'sender.primaryPhoto',
            'sender.profile',
            'talk',
        ]);

        return $this->successResponse(new TalkConversationResource($conversation), null);
    }

    /**
     * Get the previous or history of chat messages for the talk chats
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function chats(Conversation $conversation)
    {
        $userId = authUser()->id;
        if (!$this->isUserBlockedToConversation($userId, $conversation->id)) {
            $chats = Chat::query()
                ->has('user')
                ->with([
                    'conversation.talk',
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
            if ($conversation->hasLeftToTalkConversation($userId)){
                $chats->whereRaw("FIND_IN_SET(?, seen_by)", [$userId]);
            }

            $chats = $chats->latest()
                ->simplePaginate(10);

            return CustomChatResource::collection($chats);
        }
        return $this->errorResponse('You are blocked from this conversation', Response::HTTP_FORBIDDEN);

    }

    /**
     * Get talk conversation id
     *
     */
    public function getConversationId(Talk $talk){
        return $this->successResponse(new TalkConversationResource($talk->conversation), null);
    }
}
