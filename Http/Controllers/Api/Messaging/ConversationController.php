<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Enums\ChatFilterType;
use App\Enums\ChatSettingType;
use App\Enums\ChatType;
use App\Enums\ReportOptions;
use App\Enums\MuteNotificationType;
use App\Enums\ReportType;
use App\Enums\TableLookUp;
use App\Helpers\CollectionPaginate;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\CustomConversationResource;
use App\Http\Resources\EventConversationResource;
use App\Http\Resources\GroupConversationResource;
use App\Http\Resources\TalkConversationResource;
use App\Models\Chat;
use App\Models\Event;
use App\Models\Group;
use App\Models\Conversation;
use App\Models\CustomGroupChat;
use App\Models\UserCustomGroupChat;
use App\Models\MuteNotification;
use App\Models\Reports;
use App\Models\Talk;
use App\Traits\ApiResponser;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class ConversationController extends Controller
{
    use ApiResponser;

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $perPage = $request->perPage ?? 12;
        $userId = authUser()->id;

        // get all personal chats
        $personalChats = Conversation::notDeletedForUser(authUser()->id)
            ->getUserConversations(authUser()->id)
            ->with('lastChat.user', 'sender.primaryPhoto', 'receiver.primaryPhoto')
            ->has('sender')
            ->has('lastChat.message')
            ->where(function($query) use ($userId) {
                $query->where('table_lookup', TableLookUp::PERSONAL_MESSAGE)
                    ->orWhereIn('table_lookup', [TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS, TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS])
                    ->whereHas('chats', function($query) use ($userId) {
                        $query
                            ->whereHas('conversation', function($query){
                                $query->has('event')
                                    ->orHas('group');
                            })
                            ->where('user_id','!=', $userId)
                            ->where('type',0);
                    });
            })
            ->where('receiver_id','!=', 0)
            ->latest('updated_at')
            ->get();

        // get all active chat events where user is already a member, for both  rsvp type
        $eventChatsRSVP = Conversation::whereHas('attendingEvents', function ($query) {
            $query->where('user_id', authUser()->id)
                ->whereHas('event', function ($query) {
                    $query->where('live_chat_type', ChatSettingType::RSVP)
                        ->orWhere('live_chat_type', ChatSettingType::OPEN_TO_PUBLIC);
                });
        })
            ->where('table_lookup', TableLookUp::EVENTS)
            ->has('lastChat.message')
            ->get();

        // get all group chats
        $groupChats = Conversation::whereHas('attendingGroups', function ($query) {
            $query->where('user_id', authUser()->id);
        })
            ->where('table_lookup', TableLookUp::GROUPS)
            ->has('lastChat.message')
            ->get();

        /* Merge all chats */
        $allChats = $personalChats
            ->merge($eventChatsRSVP)
            ->merge($groupChats)
            ->sortByDesc('updated_at');

        $allChats = (new CollectionPaginate($allChats))->paginate($perPage);
        return $this->successResponse(
            ConversationResource::collection($allChats),
            'Success',
            Response::HTTP_OK,
            true
        );

    }

    /**
     * Get private conversations
     *
     * @return @return \Illuminate\Http\JsonResponse
     */
    public function getPrivateConversations(Request $request)
    {
        $perPage = $request->perPage ?? 25;
        $filterBy = (int) $request->filterBy ?? 0;
        $userId = authUser()->id;
        $search = $request->search ?? '';

        // private chat
        // check if private is included
        $personalChats = collect([]);
        if (collect($request->sourceBy)->contains('private')) {
            $personalChats = Conversation::notDeletedForUser($userId)
                ->getUserConversations($userId)
                ->has('lastChat.user')
                ->has($this->filterConversationBy($filterBy))
                ->searchBySenderReceiver($search, $userId)
                ->where(function($query) use ($userId) {
                    $query->where('table_lookup', TableLookUp::PERSONAL_MESSAGE)
                        ->orWhere('table_lookup', TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS)
                        ->whereHas('chats', function($query) use ($userId) {
                            $query->where('user_id','!=', $userId)
                                ->where('type',0);
                        })
                        ->orWhereIn('table_lookup', [TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS])
                        ->whereHas('chats', function($query) use ($userId) {
                            $query
                                ->whereHas('conversation', function($query){
                                    $query->has('event')
                                        ->orHas('group');
                                })
                                ->where('user_id','!=', $userId)
                                ->where('type',0);
                        });
                })
                ->where('receiver_id','!=', 0)
                ->get();
        }

        // get all custom group chat
        // check if custom is included
        $groupChats = collect([]);
        if (collect($request->sourceBy)->contains('custom')) {
            $groupChats = Conversation::notDeletedForUser($userId)
                ->filterConversations($filterBy, $userId)
                ->whereHas('attendingCustomGroups', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->whereHas('customGroup', function($query) use ($search) {
                    $query->whereHas('members', function($query) use ($search) {
                        $query->searchByPartialName($search);
                    });
                })
                ->where('table_lookup', TableLookUp::CUSTOM_GROUP_CHAT)
                ->has('lastChat.message')
                ->get();
        }

        /* Merge all chats */
        $allChats = $personalChats
            ->merge($groupChats)
            ->sortByDesc('updated_at');

        $allChats = (new CollectionPaginate($allChats))->paginate($perPage);
        return $this->successResponse(
            ConversationResource::collection($allChats),
            'Success',
            Response::HTTP_OK,
            true
        );
    }

    /**
     * Get live conversations
     *
     *  @return @return \Illuminate\Http\JsonResponse
     */
    public function getLiveConversations(Request $request)
    {
        $perPage = $request->perPage ?? 25;
        $filterBy = (int) $request->filterBy ?? 0;
        $userId = authUser()->id;
        $search = $request->search ?? '';

        // get all active chat events where user is already a member
        // check if event is included
        $eventChatsRSVP = collect([]);
        $eventChatsCheckedIns = collect([]);

        if (collect($request->sourceBy)->contains('event')) {
            $eventChatsRSVP = Conversation::notDeletedForUser($userId)
                ->filterConversations($filterBy, $userId)
                ->whereHas('attendingEvents', function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->whereHas('event', function ($query) {
                            $query->where('live_chat_type', ChatSettingType::RSVP);
                        });
                })
                ->whereHas('event', function($query) use ($search) {
                    $query->whereRaw("title LIKE ?", ["%$search%"]);
                })
                ->where('table_lookup', TableLookUp::EVENTS)
                ->has('lastChat.message')
                ->get();


            // get all event chat for Check-ins type
            $eventChatsCheckedIns = Conversation::notDeletedForUser($userId)
                ->filterConversations($filterBy, $userId)
                ->whereHas('attendingEvents', function ($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->whereHas('event', function ($query) use ($userId) {
                            $query->where('live_chat_type', ChatSettingType::CHECKED_INS)
                                ->where(function ($query) use ($userId) {
                                    $query->where('user_id', $userId)
                                        ->whereNull('attended_at')
                                        ->orWhereNotNull('attended_at');
                                });
                        });
                })
                ->where('table_lookup', TableLookUp::EVENTS)
                ->has('lastChat.message')
                ->get();
        }

        // get all group chats
        // check if group is included
        $groupChats = collect([]);

        if (collect($request->sourceBy)->contains('group')) {
            $groupChats = Conversation::notDeletedForUser($userId)
                ->filterConversations($filterBy, $userId)
                ->whereHas('attendingGroups', function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                ->whereHas('group', function($query) use ($search) {
                    $query->whereRaw("name LIKE ?", ["%$search%"]);
                })
                ->where('table_lookup', TableLookUp::GROUPS)
                ->has('lastChat.message')
                ->get();
        }

        /* Merge all chats */
        $allChats = $eventChatsRSVP
            ->merge($eventChatsCheckedIns)
            ->merge($groupChats)
            ->sortByDesc('updated_at');

        $allChats = (new CollectionPaginate($allChats))->paginate($perPage);
        return $this->successResponse(
            ConversationResource::collection($allChats),
            'Success',
            Response::HTTP_OK,
            true
        );
    }

    /**
     * @param Conversation $conversation
     *
     * @return ConversationResource
     */
    public function show(Conversation $conversation)
    {

        $conversation->load([
            'lastChat.message',
            'lastChat.user',
            'sender.primaryPhoto',
            'receiver.primaryPhoto',
            'sender.profile',
            'receiver.profile',
        ]);
        return $this->successResponse(new ConversationResource($conversation), null);

    }

    /**
     * @param CreateConversationRequest $request
     *
     * @return ConversationResource
     */
    public function create(CreateConversationRequest $request)
    {
        $userId = authUser()->id;
        // Personal message to all members of groups or events
        if (collect([TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS])->contains($request->table_lookup)){

            $chatType = $request->chat_type;

            $session = Conversation::firstOrCreate([
                'sender_id' => $userId,
                'receiver_id' => 0,
                'table_id' => $request->table_id,
                'table_lookup' => $request->table_lookup,
                'chat_type' => $chatType
            ]);

            $modifiedSession = new ConversationResource($session);
        }else{
            // 1 to 1 personal message
            $session = Conversation::where([
                'sender_id' => $request->recipient_id, 'receiver_id' => $userId,
                'table_id' => $request->table_id, 'table_lookup' => $request->table_lookup
            ])->first();

            if (!$session) {
                $session = Conversation::firstOrCreate([
                    'sender_id' => $userId, 'receiver_id' => $request->recipient_id,
                    'table_id' => $request->table_id, 'table_lookup' => $request->table_lookup
                ]);
            }

            $session->table_lookup = TableLookUp::PERSONAL_MESSAGE;
            $session->save();
            $session->load('sender.primaryPhoto', 'receiver.primaryPhoto');
            $modifiedSession = new ConversationResource($session);
        }

        return $this->successResponse($modifiedSession, null);
    }

    /**
     * Create a conversation session for the event chat
     *
     * @param Request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function createEventChat(Request $request)
    {
        $tableId = $request->table_id;
        $session = Conversation::with('event')
            ->firstOrCreate([
                'sender_id' => $tableId,
                'table_id' => $tableId,
                'table_lookup' => TableLookUp::EVENTS,
            ]);
        return $this->successResponse(new EventConversationResource($session), '', Response::HTTP_CREATED);
    }

    /**
     * Create a conversation session for the group chat
     *
     * @param Request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function createGroupChat(Request $request)
    {
        $tableId = $request->table_id;
        $session = Conversation::with('group')
            ->firstOrCreate([
                'sender_id' => $tableId,
                'table_id' => $tableId,
                'table_lookup' => TableLookUp::GROUPS,
            ]);
        return $this->successResponse(new GroupConversationResource($session), '', Response::HTTP_CREATED);
    }

    /**
     * Tag chats as seen, it will add the user who already seen the chats
     * This will also be used to get unread chats
     *
     * @param Conversation
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan
     */
    public function seen(Conversation $conversation)
    {
        try {
            $userId = authUser()->id;

            // Custom group, if user left conversation just return
            if ($conversation->table_lookup === TableLookUp::CUSTOM_GROUP_CHAT) {
                if ($conversation->hasLeftConversation($userId)) {
                    return $this->successResponse(null);
                }
            }

            if (collect([TableLookUp::PERSONAL_MESSAGE, TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS, TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS])->contains($conversation->table_lookup)) {
                $conversation->readPersonalChat();
            }else {
            // Get all chats that the current user has not read yet
            // Update seen_by if current userId doesn't exist
                $conversation->chats()
                    ->whereRaw("(NOT FIND_IN_SET({$userId}, seen_by) OR seen_by IS NULL)")
                    ->update(['seen_by' => DB::raw("IF(seen_by IS NULL, {$userId}, concat(seen_by,',{$userId}'))")]);
            }
            return $this->successResponse($conversation);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * Create a conversation session for the event inquiry chat
     * When someone is not a member in the event and inquire in the chat
     *
     * @param Request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function createEventInquiryChat(Request $request)
    {
        $tableId = $request->table_id;
        $session = Conversation::with('event')
            ->firstOrCreate([
                'sender_id' => $tableId,
                'table_id' => $tableId,
                'table_lookup' => TableLookUp::EVENT_INQUIRY,
            ]);
        $session->receiver_id = $session->event->user_id;
        $session->save();
        return $this->successResponse(new EventConversationResource($session));
    }

    /**
     * Tag chats as unseen, only get the last record
     *
     * @param Conversation
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan
     */
    public function unSeen(Conversation $conversation)
    {
        try {
            $userId = authUser()->id;
            if (collect([TableLookUp::PERSONAL_MESSAGE, TableLookUp::PERSONAL_MESSAGE_EVENTS, TableLookUp::PERSONAL_MESSAGE_GROUPS, TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS])->contains($conversation->table_lookup)) {
                $conversation->unreadPersonalChat();
            } else {
                Chat::where('chat_session_id', $conversation->id)
                    ->latest()
                    ->take(1)
                    ->update(['seen_by' => DB::raw("TRIM(BOTH ',' FROM REPLACE( CONCAT(',',seen_by,','), ',{$userId},', ',0,') )")]);
            }

            return $this->successResponse($conversation);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * Mute conversation
     *
     * @param Conversation $conversation
     *
     * @author Angelito Tan
     */
    public function mute(Conversation $conversation)
    {
        try {
            MuteNotification::firstOrCreate([
                'user_id' => authUser()->id,
                'type' => MuteNotificationType::CHAT_SESSION,
                'table_id' => $conversation->id,
            ]);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * Unmute conversation
     *
     * @param  Conversation $conversation
     * @author Angelito Tan
     */
    public function unmute(Conversation $conversation)
    {
        try {
            MuteNotification::where('user_id', authUser()->id)
                ->where('type', MuteNotificationType::CHAT_SESSION)
                ->where('table_id', $conversation->id)
                ->delete();
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * User Report
     *
     * @param Request $request
     * @author Angelito Tan
     */
    public function userReport(Request $request)
    {
        try {
            Reports::firstOrCreate([
                'reporter_id' => authUser()->id,
                'user_id' => $request->user_id,
                'type' => ReportType::CHAT_SESSION,
                'notes' => $request->reason,
            ]);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception->getMessage(), 400);
        }
    }

    /**
     * Create a custom group conversation
     *
     * @param Request
     */
    public function createCustomGroupChat(Request $request)
    {
        $userId = authUser()->id;

        // Create group
        $group = CustomGroupChat::create([
            'user_id' => $userId,
        ]);

        // merge owner and friend ids
        $memberIds = collect($userId)->merge($request->friend_ids);

        // Add members to the group
        $group
            ->userCustomGroup()
            ->sync($memberIds);

        //create a session
        $session = Conversation::with('customGroup')
            ->firstOrCreate([
                'sender_id' => $group->id,
                'table_id' => $group->id,
                'table_lookup' => TableLookUp::CUSTOM_GROUP_CHAT,
            ]);
        return $this->successResponse(new CustomConversationResource($session));
    }

    /**
     * Create a talk conversation
     *
     * @param Request
     */
    public function createTalkChat(Request $request, Talk $talk)
    {
        //create a session
        $session = Conversation::with('talk')
            ->firstOrCreate([
                'sender_id' => $talk->owner_id,
                'table_id' => $talk->id,
                'table_lookup' => TableLookUp::TALK,
            ]);
        return $this->successResponse(new TalkConversationResource($session));
    }

    /**
     * @author Angelito Tan
     */
    public function leaveConversation (Request $request){
        $userId = authUser()->id;
        $tableId = $request->table_id;
        UserCustomGroupChat::where('user_id', $userId)
            ->where('custom_group_id', $tableId)
            ->update(['has_left' => ChatFilterType::HAS_LEFT_CONVERSATION]);
        return $this->successResponse(null);
    }

    /**
     * @author Angelito Tan
     */
    public function joinConversation (Request $request){
        $userId = authUser()->id;
        $tableId = $request->table_id;
        UserCustomGroupChat::where('user_id', $userId)
            ->where('custom_group_id', $tableId)
            ->update(['has_left' => ChatFilterType::STILL_IN_CONVERSATION]);
        return $this->successResponse(null);
    }

    /**
     * filter conversation
     *
     */
    public function filterConversationBy(int $filterBy)
    {
        // Get all read and unread message
        if ($filterBy === ChatFilterType::ALL) {
            return 'lastChat.message';
        }

        // Get read message
        if ($filterBy === ChatFilterType::READ) {
            return 'lastChat.readMessages';
        }

        // Get unread message
        if ($filterBy === ChatFilterType::UNREAD) {
            return 'lastChat.unreadMessages';
        }
    }

    /**
     * create conversation for all pf users
     *
     */
    public function createToAllPFUsers() {
        $userId = authUser()->id;
        $session = Conversation::firstOrCreate([
            'sender_id' => $userId,
            'receiver_id' => 0,
            'table_id' => 0,
            'table_lookup' => TableLookUp::PERSONAL_MESSAGE_TO_ALL_PF_USERS
        ]);
        return $this->successResponse(new ConversationResource($session));
    }

    /**
     * Get report lists
     * @author Angelito Tan
     */

    public function reportLists(){
        return $this->successResponse(ReportOptions::map());
    }
}
