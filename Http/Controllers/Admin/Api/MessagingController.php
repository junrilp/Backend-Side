<?php

namespace App\Http\Controllers\Admin\Api;

use Throwable;
use App\Models\Chat;
use App\Models\Event;
use App\Models\Group;
use App\Enums\TableLookUp;
use App\Traits\AdminTraits;
use App\Models\Conversation;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\ChatSettingType;
use App\Enums\ChatBlockedStatus;
use App\Enums\UserStatus;
use App\Events\PrivateChatForEvent;
use App\Events\PrivateChatForGroup;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventChatResource;
use App\Http\Resources\GroupChatResource;
use App\Events\PrivateChatMessageForEvent;
use App\Events\PrivateChatMessageForGroup;
use Illuminate\Support\Facades\Notification;
use App\Repository\Messaging\MessagingRepository;
use App\Repository\Connection\ConnectionRepository;
use App\Http\Controllers\Api\Messaging\ChatController;
use App\Models\User;
use App\Notifications\Messaging\EventMessageNotification;
use App\Notifications\Messaging\GroupMessageNotification;

class MessagingController extends Controller
{
    use ApiResponser;
    use AdminTraits;

    protected $chatController;
    private $connection;

    public function __construct(ChatController $chatController, ConnectionRepository $connection)
    {
        $this->chatController = $chatController;
        $this->connection = $connection;
    }

    public function sendMessage(Request $request)
    {
        try {
            // SEND MESSAGE TO USER
            if ($request->type === 'Selected' && $request->send_to === 'User') {
                // If admin choose selected user. We will send the message to those users who are selected
                $users = $request->user_ids;
                foreach ($users as $user) {
                    $this->createUserConversation($request, (int)$user['id']);
                }
            }
            if ($request->type === 'allUser' && $request->send_to === 'User') {
                // If admin choose all user. We will send the message to those all users available in our db
                $users = User::where('status', UserStatus::PUBLISHED)->get();
                foreach ($users as $user) {
                    $this->createUserConversation($request, (int)$user->id);
                }
            }
            if ($request->type === 'Birthdate' && $request->send_to === 'User') {
                $birthdayCelebrants = $this->connection->getBirthdayCelebrants($request->all());
                foreach ($birthdayCelebrants as $user) {
                    $this->createUserConversation($request, (int)$user->id);
                }
            }

            // SEND MESSAGE TO GROUP
            if ($request->type === 'Selected' && $request->send_to === 'Group') {
                // If admin choose selected group. We will send the message to those users who are selected
                $groups = $request->user_ids;
                foreach ($groups as $group) {
                    $conversation = $this->createGroupConversation($request, (int)$group['id']);
                    return $this->sendMessageToGroup($request, $conversation);
                }
            }
            if ($request->type === 'All Group' && $request->send_to === 'Group') {
                $groups = Group::whereNotNull('published_at')->get();
                foreach ($groups as $group) {
                    $conversation = $this->createGroupConversation($request, (int)$group->id);
                    return $this->sendMessageToGroup($request, $conversation);
                }
            }

            // SEND MESSAGE TO EVENT
            if ($request->type === 'Selected' && $request->send_to === 'Event') {
                // If admin choose selected group. We will send the message to those users who are selected
                $events = $request->user_ids;
                foreach ($events as $event) {
                    $conversation = $this->createEventConversation($request, (int)$event['id']);
                    return $this->sendMessageToEvent($request, $conversation);
                }
            }
            if ($request->type === 'All Event' && $request->send_to === 'Event') {
                $events = Event::where('is_published', true)->get();
                foreach ($events as $event) {
                    $conversation = $this->createEventConversation($request, (int)$event->id);
                    return $this->sendMessageToEvent($request, $conversation);
                }
            }

            $this->successResponse([], 'Successfully Sent');
        } catch (Throwable $exception) {
            Log::critical('UserMessagingController::sendMessage ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public static function createUserConversation(Request $request, $userId)
    {
        $session = Conversation::where(['sender_id' => authUser()->id, 'receiver_id' => $userId])->first();
        
        if (!$session) {
            $session = Conversation::firstOrCreate(['sender_id' => authUser()->id, 'receiver_id' => $userId]);
        }
        
        $session->table_lookup = TableLookUp::PERSONAL_MESSAGE;
        $session->deleted_for_user = null;
        $session->save();
        return MessagingRepository::sendMessage(authUser(), $session, $request->all());
    }

    public static function createGroupConversation(Request $request, $tableId)
    {
        return Conversation::with('group')
        ->firstOrCreate([
            'sender_id' => $tableId,
            'table_id' => $tableId,
            'table_lookup' => TableLookUp::GROUPS,
        ]);
    }

    public static function createEventConversation(Request $request, $tableId)
    {
        return Conversation::with('event')
        ->firstOrCreate([
            'sender_id' => $tableId,
            'table_id' => $tableId,
            'table_lookup' => TableLookUp::EVENTS,
        ]);
    }

    public function sendMessageToGroup(Request $request, $conversation)
    {
        // broadcast only to enabled chat
        // checking if admin suddenly turn off the chat while conversation is still on-going
        // Blocked user are not allowed to reply in the chats
        $userId = authUser()->id;

        // Check if group is published
        if (!$conversation->group->published_at) {
            return $this->errorResponse('Chat not allowed, group is not published', Response::HTTP_FORBIDDEN);
        }

        $attachments = $request->post('attachments', []);
        
        //if there's an attachment, allow empty message
        $content = $request->post('content');
        if (!$content && $attachments !== '') {
            $content = '';
        }

        $message = $conversation->messages()->create(['content' => $content]); // create the message
        $recipientChatMessage = $message->createForSend($conversation->id); // store the message

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

    public function sendMessageToEvent(Request $request, $conversation)
    {
        // broadcast only to enabled chat
        // checking if admin suddenly turn off the chat while conversation is still on-going
        // Blocked user are not allowed to reply in the chats
        $userId = authUser()->id;

        // Check if event is published
        if (!$conversation->event->is_published){
            return $this->errorResponse('Chat not allowed, event is not published', Response::HTTP_FORBIDDEN);
        }

        // Check if user is a member from the event
        if ($conversation->event->live_chat_type !== ChatSettingType::OPEN_TO_PUBLIC) { // If chat type is not open to the public
            if (!in_array($userId, $conversation->event->attendees()->pluck('user_id')->toArray()) && !$this->isAdmin()) {
                return $this->errorResponse('Chat not allowed, you are not a member of this event', Response::HTTP_FORBIDDEN);
            }
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
            ->whereNotIn('user_id', $mergeIds)
            ->where('is_chat_blocked', ChatBlockedStatus::NOT_BLOCKED)
            ->get();

        // Send a broadcast to the widget
        // For widget purpose only
        // This return a data and will be show as a widget if not yet exist in the 'conversation' store
        // Note sender_id is equivalent to the event_id
        $receipients->each(function($user) use ($recipientChatMessage) {
            broadcast(new PrivateChatMessageForEvent($recipientChatMessage, $user->id));
        });

        Notification::send(
            $receipients,
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

        return $this->successResponse(new EventChatResource($chat), '', Response::HTTP_CREATED);
    }
}
