<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Enums\ChatBlockedStatus;
use App\Enums\ChatSettingType;
use App\Enums\ChatType;
use App\Enums\TableLookUp;
use App\Enums\UserStatus;
use App\Events\BlockUserFromConversationEvent;
use App\Events\MessageReadEvent;
use App\Events\PrivateChatEvent;
use App\Events\PrivateUserChatMessageEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatRequest;
use App\Http\Requests\SendToAllUsersRequest;
use App\Http\Requests\ClearConversationRequest;
use App\Http\Requests\SendChatMessageRequest;
use App\Http\Resources\ChatResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\FriendChatResource;
use App\Jobs\SendPersonalMessageEmail;
use App\Models\BlockedUserChat;
use App\Models\Chat;
use App\Models\ChatReply;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserCustomGroupChat;
use App\Models\UserEvent;
use App\Models\UserGroup;
use App\Notifications\Messaging\PrivateMessageNotification;
use App\Traits\ApiResponser;
use App\Traits\ChatBlockedUserTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendEventMessageToAllMembersJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Jobs\SendMessageToAllPFUsersJob;

class ChatController extends Controller
{
    use ApiResponser;
    use ChatBlockedUserTrait;

    /**
     * @param SendChatMessageRequest $request
     * @param Conversation $conversation
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function send(ChatRequest $request, Conversation $conversation)
    {
        $currentUser = authUser();

        $attachments = $request->post('attachments', []);
        //if there's an attachment, allow empty message
        $content = $request->post('content');
        if (!$content && $attachments !== '') {
            $content = '';
        }

        $message = $conversation->messages()->create(['content' => $content]);
        $chat = $message->createForSend($conversation->id);

        $toUser = $conversation->sender_id == authUser()->id ? $conversation->receiver_id : $conversation->sender_id;

        $recipientChatMessage = $message->createForReceive($conversation->id, $toUser);

        // If reply is found
        if ($request->post('reply_to')) {
            // create a reply for the send
            ChatReply::create([
                'chat_id' => $chat->id,
                'reply_to_id' => $request->post('reply_to'),
                'reply_to_media_id' => $request->post('reply_to_media_id'),
            ]);

            // create a reply for the receive
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

        // Send a broadcast to the widget
        // For widget purpose only
        // This return a data and will be show as a widget if not yet exist in the 'conversation' store
        broadcast(new PrivateUserChatMessageEvent($currentUser, $recipientChatMessage->user, $recipientChatMessage));
        Notification::send(
            $recipientChatMessage->user,
            new PrivateMessageNotification($recipientChatMessage)
        );

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

        return $this->successResponse(new ChatResource($chat), '', Response::HTTP_CREATED);

    }

    /**
     * Send a group personal message
     *
     * @param ChatRequest $request
     */
    public function sendToMembers(ChatRequest $request) {

        $currentUser = authUser();
        $userId = authUser()->id;
        $chatType = $request->chat_type;
        $content = $request->post('content');
        $attachments = $request->post('attachments', []);

        //if there's an attachment, allow empty message
        if (!$content && $attachments !== '') {
            $content = '';
        }

        // Events
        $userLists = [];
        if (TableLookUp::PERSONAL_MESSAGE_EVENTS === $request->table_lookup) {
            //get all users by chat-type
            $eventQuery = UserEvent::where('event_id', $request->table_id)
                ->whereHas('user', function($query) use ($userId) {
                    $query->where('status', UserStatus::PUBLISHED)
                        ->where('user_id', '!=', $userId);
                });

            $userLists = (clone $eventQuery)
                        ->when($chatType=== ChatType::ATTENDING, function($query) {
                            // list of user for attending
                            return $query->whereNotNull('qr_code')
                                ->where('owner_flagged', 0);
                        })
                        ->when($chatType=== ChatType::CHECKED_IN, function($query) {
                            // list of user for checked-in
                            return $query->whereNotNull('attended_at')
                                ->where('owner_flagged', 0);
                        })
                        ->when($chatType=== ChatType::WAIT_LISTED, function($query) {
                            // list of user for wait listed
                            return $query->whereNull('qr_code')
                                ->where('owner_flagged', 0);
                        })
                        ->when($chatType=== ChatType::INVITED, function($query) {
                            // list of user for invited
                            return $query->whereNotNull('qr_code')
                                ->where('owner_flagged', 0);
                        })
                        ->when($chatType=== ChatType::AWAITING_INVITE, function($query) {
                            // list of user for awaiting invite or not invited
                            return $query->where(function($query) {
                                $query->whereNull('qr_code')
                                    ->where('owner_flagged', 0);
                            });
                        })
                        ->when($chatType=== ChatType::FLAGGED, function($query) {
                            // list of user for flagged
                            return $query->where('owner_flagged', 1);
                        })
                        ->pluck('user_id');
        }

        // Groups
        if (TableLookUp::PERSONAL_MESSAGE_GROUPS === $request->table_lookup) {
            $userLists = UserGroup::where('group_id', $request->table_id)
                        ->whereHas('user', function($query) use ($userId) {
                            // $query->whereNotNull('mobile_number')
                            $query->where('status', UserStatus::PUBLISHED)
                                ->where('user_id', '!=', $userId);
                        })
                        ->pluck('user_id');
        }

        $conversation = Conversation::firstOrCreate([
            'sender_id' => $userId,
            'receiver_id' => 0,
            'table_id' => $request->table_id,
            'table_lookup' => $request->table_lookup,
            'chat_type' => $chatType
        ]);

        $message = $conversation->messages()->create(['content' => $content]);
        $chat = $message->createForSend($conversation->id);
        $message->createForReceive($conversation->id, $conversation->receiver_id);
        $conversation->unDeletedAllUsers();
        $conversation->has_message = true;
        $conversation->save();

        // Attachments
        $media = collect($attachments)->map(function ($row) {
            return [
                'media_id' => $row,
            ];
        });
        $message->addMedia($media);

        // broadcast(new PrivateChatEvent($chat, $message->content));

        SendEventMessageToAllMembersJob::dispatch(
            [
                'user_list' => $userLists,
                'user_id' => $userId,
                'media' => $media,
                'table_id' => $request->table_id,
                'table_lookup' => $request->table_lookup,
                'content' => $content,
                'user' => $currentUser

            ])->onQueue('high');

        $chat = Chat::with('user.primaryPhoto',
            'message.conversation.sender.primaryPhoto',
            'message.conversation.receiver.primaryPhoto',
            'message.media')
            ->find($chat->id);

        return $this->successResponse(new ChatResource($chat));
    }

    /**
     * Send a group personal message to all PF users
     *
     * @param SendToAllUsersRequest $request
     */
    public function sendToAllPFUsers(SendToAllUsersRequest $request, Conversation $conversation) {
        $content = $request->post('content');
        $attachments = $request->post('attachments', []);

        $userLists = User::where('status', UserStatus::PUBLISHED)
                            ->where('id', '!=', authUser()->id)
                            ->whereNull('deleted_at')
                            ->get();

        //if there's an attachment, allow empty message
        if (!$content && $attachments !== '') {
            $content = '';
        }

        // Attachments
        $media = collect($attachments)->map(function ($row) {
            return [
                'media_id' => $row,
            ];
        });

        // For history record of sent message
        $message = $conversation->messages()->create(['content' => $content]);
        $chat = $message->createForSend($conversation->id);
        $message->createForReceive($conversation->id, $conversation->receiver_id);
        $conversation->unDeletedAllUsers();
        $conversation->has_message = true;
        $conversation->save();

        // Attachments
        $message->addMedia($media);

        SendMessageToAllPFUsersJob::dispatch([
            'user_list' => $userLists,
            'media' => $media,
            'table_id' => 0,
            'table_lookup' => TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS,
            'content' => $content,
            'user' => authUser()
        ])->onQueue('high');

        return $this->successResponse(new ChatResource($chat));

    }

    /**
     * @param Conversation $conversation
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function chats(Conversation $conversation)
    {
        $userId = authUser()->id;
        if (!$this->isUserBlockedToConversation($userId, $conversation->id)) {
            $chats = Chat::query()
                ->has('user')
                ->with([
                    'user.primaryPhoto',
                    'message.conversation.sender.primaryPhoto',
                    'message.conversation.receiver.primaryPhoto',
                    'message.media',
                    'reply.repliedTo.user',
                    'reply.repliedTo.message',
                ])
                ->where('chat_session_id', $conversation->id)
                ->where('user_id', $userId)
                ->whereRaw("(NOT FIND_IN_SET(?, deleted_for_user) OR deleted_for_user IS NULL)", [$userId]) // remove chat deleted before for user
                ->latest()->simplePaginate(10);
            return ChatResource::collection($chats);
        }
        return $this->errorResponse('You are blocked from this conversation', Response::HTTP_FORBIDDEN);
    }

    /**
     * @param Conversation $conversation
     *
     * @return mixed
     */
    public function read(Conversation $conversation)
    {
        try {
            $chats = $conversation->chats->where('read_at', null)->where('type', 0);

            foreach ($chats as $chat) {
                $chat->update(['read_at' => now()]);
                broadcast(new MessageReadEvent(new ChatResource($chat), $chat->chat_session_id));
            }

            return $this->successResponse($chats, 'Marked as read');
        } catch (\Throwable $exception) {
            return $this->errorResponse('We are unable to mark the conversation as read.', 400);
        }
    }

    /**
     * @param ClearConversationRequest $request
     * @param Conversation $conversation
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function clear(ClearConversationRequest $request, Conversation $conversation)
    {
        DB::transaction(function () use ($conversation) {
            $userId = authUser()->id;
            if (!in_array($userId, $conversation->getDeletedUsers())) {
                $conversation->setDeletedByUser($userId);

                // Remove the chats from user, only new conversation will show
                $conversation->chats()
                    ->whereRaw("(NOT FIND_IN_SET(?, deleted_for_user) OR deleted_for_user IS NULL)",[$userId])
                    ->update(['deleted_for_user' => DB::raw("IF(deleted_for_user IS NULL, {$userId}, concat(deleted_for_user,',{$userId}'))")]);
            }
            $conversation->save();
        });
        return $this->successResponse(ConversationResource::make($conversation));
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFriends(Request $request)
    {

        $search = $request->search;
        $perPage = $request->perPage ?? 10;
        $userId = authUser()->id;
        $user = User::with('media')
            ->where('id', '!=', $userId)
            ->where('status', UserStatus::PUBLISHED)
            ->orWhere('status', UserStatus::VERIFIED)
            ->searchByPartialName($search)
            ->paginate($perPage);

        return $this->successResponse(
            FriendChatResource::collection($user),
            'Success',
            Response::HTTP_OK,
            $request->perPage > 0
        );

    }

    /**
     * Block user from the chat
     *
     * @author Angelito Tan
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function blockUser(Request $request)
    {
        $sessionId = $request->session_id;
        $blockUserId = $request->block_user_id;
        $tableLookUp = $request->table_lookup;
        $tableId = $request->table_id;

        if ($tableLookUp == TableLookUp::EVENTS) {
            UserEvent::where('event_id', $tableId)
                ->where('user_id', $blockUserId)
                ->update(['is_chat_blocked' => ChatBlockedStatus::IS_BLOCKED]);
        }

        if ($tableLookUp == TableLookUp::GROUPS) {
            UserGroup::where('group_id', $tableId)
                ->where('user_id', $blockUserId)
                ->update(['is_chat_blocked' => ChatBlockedStatus::IS_BLOCKED]);
        }

        if ($tableLookUp == TableLookUp::CUSTOM_GROUP_CHAT) {
            UserCustomGroupChat::where('custom_group_id', $tableId)
                ->where('user_id', $blockUserId)
                ->update(['is_chat_blocked' => ChatBlockedStatus::IS_BLOCKED]);
        }

        BlockedUserChat::firstOrCreate([
            'user_id' => authUser()->id,
            'block_user_id' => $blockUserId,
            'chat_session_id' => $sessionId,
        ]);

        broadcast(new BlockUserFromConversationEvent($sessionId, $blockUserId, true));

        return $this->successResponse(null);
    }

    /**
     * Unblock user from the chat
     *
     * @author Angelito Tan
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function unBlockUser(Request $request)
    {
        $sessionId = $request->session_id;
        $blockUserId = $request->block_user_id;
        $tableLookUp = $request->table_lookup;
        $tableId = $request->table_id;

        if ($tableLookUp == TableLookUp::EVENTS) {
            UserEvent::where('event_id', $tableId)
                ->where('user_id', $blockUserId)
                ->update(['is_chat_blocked' => ChatBlockedStatus::NOT_BLOCKED]);
        }

        if ($tableLookUp == TableLookUp::GROUPS) {
            UserGroup::where('group_id', $tableId)
                ->where('user_id', $blockUserId)
                ->update(['is_chat_blocked' => ChatBlockedStatus::NOT_BLOCKED]);
        }

        if ($tableLookUp == TableLookUp::CUSTOM_GROUP_CHAT) {
            UserCustomGroupChat::where('custom_group_id', $tableId)
                ->where('user_id', $blockUserId)
                ->update(['is_chat_blocked' => ChatBlockedStatus::NOT_BLOCKED]);
        }

        BlockedUserChat::where('user_id', authUser()->id)
            ->where('block_user_id', $blockUserId)
            ->where('chat_session_id', $sessionId)
            ->delete();

        broadcast(new BlockUserFromConversationEvent($sessionId, $blockUserId, false));

        return $this->successResponse(null);

    }

    /**
     * Get chat type settings
     *
     * @return Enums
     */
    public function chatTypeSettings()
    {
        return $this->successResponse(ChatSettingType::map());
    }
}
