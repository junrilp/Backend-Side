<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Events\PrivateChatForEvent;
use App\Events\PrivateChatMessageForEvent;
use App\Events\PrivateChatMessageForEventInquiry;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventChatResource;
use App\Http\Resources\EventConversationResource;
use App\Models\Chat;
use App\Models\ChatReply;
use App\Models\Conversation;
use App\Notifications\Messaging\EventMessageNotification;
use App\Traits\ApiResponser;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Enums\ChatBlockedStatus;
use App\Enums\ChatSettingType;
use App\Forms\MediaForm;
use App\Http\Requests\ChatRequest;
use App\Traits\AdminTraits;
use App\Jobs\SendEventMessageJob;
use Illuminate\Http\Response;
use App\Enums\IsCaseType;
use App\Mail\LiveChatEmailNotification;
use Illuminate\Support\Facades\Mail;

class EventChatController extends Controller
{
    use ApiResponser;
    use ChatBlockedUserTrait;
    use AdminTraits;

    /**
     * Send a new message to the event chat
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

        // Check if event is deleted or existed
        if (!$conversation->event){
            return $this->errorResponse('Event doesn\'t exist or already deleted', Response::HTTP_FORBIDDEN);
        }

        // Check if event is published
        if (!$conversation->event->is_published){
            return $this->errorResponse('Chat not allowed, event is not published', Response::HTTP_FORBIDDEN);
        }

        // Check if user is a member from the event
        //if ($conversation->event->live_chat_type !== ChatSettingType::OPEN_TO_PUBLIC) { // If chat type is not open to the public
        if (!in_array($userId, $conversation->event->attendees()->pluck('user_id')->toArray())) {
            return $this->errorResponse('Chat not allowed, you are not a member of this event', Response::HTTP_FORBIDDEN);
        }
        //}

        // Check user if blocked from the event chat
        if ($this->isUserBlockedToConversation($userId, $conversation->id)) {
            return $this->errorResponse('You are blocked from this event chat', Response::HTTP_FORBIDDEN);
        }

        // Check if chat is enabled
        // Temporary disable
        // if (!$conversation->event->live_chat_enabled){
        //     return $this->errorResponse('Chat for this event is disabled', Response::HTTP_FORBIDDEN);
        // }

        $attachments = $request->post('attachments', []);

        //if there's an attachment, allow empty message
        $content = $request->post('content');
        if( !$content && $attachments !== '') {
            $content = '';
        }

        $message = $conversation->messages()->create(['content' => $content]); // create the message
        $recipientChatMessage = $message->createForSend($conversation->id); // store the message

        // Sent a broadcast to the chat box
        // This update the current chat / messages
        broadcast(new PrivateChatForEvent($recipientChatMessage, $conversation));

        // If reply is found
        if ($request->post('reply_to')) {
            ChatReply::create([
                'chat_id'           => $recipientChatMessage->id,
                'reply_to_id'       => $request->post('reply_to'),
                'reply_to_media_id' => $request->post('reply_to_media_id')
            ]);
        }
        $conversation->unDeletedAllUsers();
        $conversation->has_message = true;
        $conversation->save();
        $conversation->touch();

        // Attachments
        $media = collect($attachments)->map(function($row){
            return [
                'media_id' => $row
            ];
        });
        $message->addMedia($media);

        $recipientChatMessage->load([
            'conversation',
            'message.media',
        ]);
        $eventId = $conversation->sender_id;

        // Send a notification to all event member users, except the user who send the chat message and user not muted
        $mutedUserIds = $recipientChatMessage->conversation->getMutedConversationNotifications()->pluck('user_id');
        $mergeIds = $mutedUserIds->merge($userId);

        $receipients = $recipientChatMessage->conversation->event->attendees()
            ->when($conversation->event->live_chat_type === ChatSettingType::CHECKED_INS, function($query){
                return $query->whereNotNull('attended_at');
            })
            ->whereNotIn('user_id', $mergeIds)
            ->where('is_chat_blocked', ChatBlockedStatus::NOT_BLOCKED)
            ->get();

        SendEventMessageJob::dispatch($receipients, $eventId, $recipientChatMessage)->onQueue('high');

        $chat = Chat::with('user.primaryPhoto',
            'message.conversation.sender.primaryPhoto',
            'message.conversation.receiver.primaryPhoto',
            'message.media')
            ->find($recipientChatMessage->id);

        return $this->successResponse(new EventChatResource($chat));
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
            'event',
        ]);

        return $this->successResponse(new EventConversationResource($conversation));
    }

    /**
     * Get the previous or history of chat messages for the events
     *
     * @param Conversation $conversation
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     * @author Angelito Tan
     */
    public function chats(Conversation $conversation)
    {
        $userId = authUser()->id;
        if (!$this->isUserBlockedToConversation($userId, $conversation->id)){
            $chats = Chat::query()
                ->has('user')
                ->with([
                    'conversation.event',
                    'user.primaryPhoto',
                    'message.conversation.sender.primaryPhoto',
                    'message.conversation.receiver.primaryPhoto',
                    'message.media',
                    'reply.repliedTo.user',
                    'reply.repliedTo.message',
                ])
                ->where('chat_session_id', $conversation->id)
                ->whereRaw("(NOT FIND_IN_SET(?, deleted_for_user) OR deleted_for_user IS NULL)",[$userId])
                ->latest()
                ->simplePaginate(10);
                return EventChatResource::collection($chats);
        }
        return $this->errorResponse('You are blocked from this conversation', Response::HTTP_FORBIDDEN);

    }

    /**
     * Inquiry sending for event chat
     * If user is not a member of the event this will be use to communicate to the admin and the user
     *
     * @return $request
     * @author Angelito Tan
     */
    public function sendInquiry(Request $request, Conversation $conversation)
    {
        // send only to enabled chat
        // checking if admin suddenly turn off the chat while conversation is still on-going
        // broadcast only to enabled chat
        if ($conversation->event->live_chat_enabled) {
            $message = $conversation->messages()->create(['content' => $request->post('content')]); // create the message
            $recipientChatMessage = $message->createForSend($conversation->id); // store the message

            // If reply is found
            if ($request->post('reply_to')) {
                ChatReply::create(['chat_id' => $recipientChatMessage->id, 'reply_to_id' => $request->post('reply_to')]);
            }
            $conversation->unDeletedAllUsers();
            $conversation->has_message = true;
            $conversation->save();
            //$conversation->touch();

            $attachments = $request->post('attachments', []);
            foreach ($attachments as $attachment) {

                $media = MediaForm::addMediaBase64($attachment['file'], 0, 0, $attachment['name']);

                $message->addMedia($media);

            }

            $recipientChatMessage->load([
                'conversation',
                'message.media',
            ]);

            $eventId = $conversation->event->id;
            $userReceiverId = authUser()->id === $conversation->event->user_id ? $conversation->sender_id : $conversation->receiver_id;

            // Send a broadcast to the widget
            // For widget purpose only
            // This return a data and will be show as a widget if not yet exist in the 'conversation' store
            broadcast(new PrivateChatMessageForEventInquiry($recipientChatMessage, $userReceiverId, $eventId));

            // Send a notification to user
            // Check who will received the notification
            $sendTo = authUser()->id === $conversation->event->user_id ? $conversation->sender : $conversation->receiver;
            Notification::send(
                $sendTo,
                new EventMessageNotification($eventId, $recipientChatMessage)
            );

            // Sent a broadcast to the chat box
            // This update the current chat / messages
            broadcast(new PrivateChatForEvent($recipientChatMessage, $conversation));


            $chat = Chat::with('user.primaryPhoto',
                'message.conversation.sender.primaryPhoto',
                'message.conversation.receiver.primaryPhoto',
                'message.media')
                ->find($recipientChatMessage->id);

            return $this->successResponse(new EventChatResource($chat));
        }
        return $this->successResponse(null);
    }
}
