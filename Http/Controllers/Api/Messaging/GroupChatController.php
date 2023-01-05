<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Enums\ChatBlockedStatus;
use App\Events\PrivateChatForGroup;
use App\Events\PrivateChatMessageForGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Http\Resources\GroupChatResource;
use App\Http\Resources\GroupConversationResource;
use App\Models\Chat;
use App\Models\ChatReply;
use App\Models\Conversation;
use App\Notifications\Messaging\GroupMessageNotification;
use App\Forms\MediaForm;
use App\Traits\AdminTraits;
use App\Traits\ApiResponser;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;

class GroupChatController extends Controller
{
    use ApiResponser;
    use ChatBlockedUserTrait;
    use AdminTraits;

    /**
     * Send a new message to the group chat
     *
     * @return $request
     * @author Angelito Tan
     */
    public function send(ChatRequest $request, Conversation $conversation)
    {
        // broadcast only to enabled chat
        // checking if admin suddenly turn off the chat while conversation is still on-going
        // Blocked user are not allowed to reply in the chats
        $userId = authUser()->id;

        // Check if group is deleted or existed
        if (!$conversation->group){
            return $this->errorResponse('Group doesn\'t exist or already deleted', Response::HTTP_FORBIDDEN);
        }

        // Check if group is published
        if (!$conversation->group->published_at) {
            return $this->errorResponse('Chat not allowed, group is not published', Response::HTTP_FORBIDDEN);
        }


        // Check if user is a member from the group
        if (!in_array($userId, $conversation->group->members()->pluck('user_id')->toArray()) && !$this->isAdmin()) {
            return $this->errorResponse('Chat not allowed, you are not a member of this group', Response::HTTP_FORBIDDEN);
        }

        // Check user if blocked from the group chat
        if ($this->isUserBlockedToConversation($userId, $conversation->id)) {
            return $this->errorResponse('You are blocked from this group chat', Response::HTTP_FORBIDDEN);
        }

        // Check if chat is disabled
        // Temporary disable
        // if (!$conversation->group->live_chat_enabled) {
        //     return $this->errorResponse('Chat for this group is disabled', Response::HTTP_FORBIDDEN);
        // }

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

        // Show in the bell icon notification
        // Send a notification to all group member users, except the user who send the chat message and user not muted
        $mutedUserIds = $recipientChatMessage->conversation->getMutedConversationNotifications()->pluck('user_id');
        $mergeIds = $mutedUserIds->merge($userId);
        $receipients = $recipientChatMessage->conversation->group->members()
            ->whereNotIn('user_id', $mergeIds)
            ->where('is_chat_blocked', ChatBlockedStatus::NOT_BLOCKED)
            ->get();

        // Send a broadcast to the widget
        // For widget purpose only
        // This return a data and will be show as a widget if not yet exist in the 'conversation' store
        $receipients->each(function($user) use ($recipientChatMessage) {
            broadcast(new PrivateChatMessageForGroup($recipientChatMessage, $user->id));
        });

        Notification::send(
            $receipients,
            new GroupMessageNotification($groupId, $recipientChatMessage)
        );

        // Sent a broadcast to the chat box
        // This update the current chat / messages
        broadcast(new PrivateChatForGroup($recipientChatMessage, $conversation));

        $chat = Chat::with('user.primaryPhoto',
            'message.conversation.sender.primaryPhoto',
            'message.conversation.receiver.primaryPhoto',
            'message.media')
            ->find($recipientChatMessage->id);

        return $this->successResponse(new GroupChatResource($chat), '', Response::HTTP_CREATED);
    }

    /**
     * Get the last message received
     *
     * @param Conversation $conversation
     * @return void
     * @author Angelito Tan
     */
    public function show(Conversation $conversation)
    {
        $conversation->load([
            'lastChat.message',
            'lastChat.user',
            'sender.primaryPhoto',
            'sender.profile',
            'group',
        ]);

        return $this->successResponse(new GroupConversationResource($conversation));
    }

    /**
     * Get the previous or history of chat messages for the group
     *
     * @param Conversation $conversation
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Angelito Tan
     */
    public function chats(Conversation $conversation)
    {
        $userId = authUser()->id;
        if (!$this->isUserBlockedToConversation($userId, $conversation->id)) {
            $chats = Chat::query()
                ->has('user')
                ->with([
                    'conversation.group',
                    'user.primaryPhoto',
                    'message.conversation.sender.primaryPhoto',
                    'message.conversation.receiver.primaryPhoto',
                    'message.media',
                    'reply.repliedTo.user',
                    'reply.repliedTo.message',
                ])
                ->where('chat_session_id', $conversation->id)
                ->whereRaw("(NOT FIND_IN_SET(?, deleted_for_user) OR deleted_for_user IS NULL)", [$userId])
                ->latest()
                ->simplePaginate(10);
            return GroupChatResource::collection($chats);
        }
        return $this->errorResponse('You are blocked from this conversation', Response::HTTP_FORBIDDEN);

    }
}
