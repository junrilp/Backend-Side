<?php

namespace App\Repository\Invitation;

use App\Enums\GatheringType;
use App\Enums\GroupMemberInviteStatus;
use App\Jobs\SendInvite;
use App\Jobs\SendInviteToFriends;
use App\Mail\EventInvitation;
use App\Models\EmailInvite;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMemberInvite;
use App\Models\RoleUser;
use App\Models\User;
use App\Models\UserEvent;
use App\Models\UserGroup;
use App\Notifications\GroupInvitationReceivedNotification;
use App\Notifications\HuddleInvitationNotification;
use App\Notifications\PastEventInvitationNotification;
use App\Repository\Invitation\InvitationInterface;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class InvitationRepository implements InvitationInterface
{
    /**
     * @param User $user
     * @param mixed $keyword
     * @param Int $perPage
     * Combine past events and groups table
     *
     * @return collection
     */
    public function listInvitePastEvents(User $user, $keyword, Int $perPage)
    {

        $pastEvents = $user->pastEvents()
            ->select(
                'events.id',
                DB::raw("'event' AS past_type"),
                'events.title',
                'events.slug',
                'events.user_id'
            )
            ->whereHas('userEvents', function ($query) {
                $query->where('user_id', '!=', authUser()->id);
            })->where('title', 'like', "%" . $keyword . "%");

        $groups = $user->myGroups()
            ->select(
                'groups.id',
                DB::raw("'group' AS past_type"),
                DB::raw("groups.name AS title"),
                'groups.slug',
                'groups.user_id'
            )
            ->whereHas('members', function ($query) {
                $query->where('user_id', '!=', authUser()->id);
            })->isNotPf();

        return $pastEvents->union($groups)
            ->paginate($perPage);
    }
    /**
     * Invite user from past events
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function invitePastEvents(array $request, $source)
    {

        if (Arr::has($request, 'past_events_ids')) {

            self::insertInviteEmail($source, $request['past_events_ids']);

            SendInvite::dispatch($source);

        }
    }

    public function getPastEventsUsers($resource)
    {

        $pastEventsIds = EmailInvite::where('past_resource', 'App\Models\Event')
            ->where('resource', get_class($resource))
            ->where('resource_id', $resource->id)
            ->get()
            ->pluck('past_resource_id')->toArray();

        $pastGroupIds = EmailInvite::where('past_resource', 'App\Models\Group')
            ->where('resource', get_class($resource))
            ->where('resource_id', $resource->id)
            ->get()
            ->pluck('past_resource_id')->toArray();

        $pastEventAttendees = UserEvent::whereIn('event_id', $pastEventsIds)
            ->get()
            ->unique('user_id')
            ->pluck('user_id');

        $pastGroupMembers = UserGroup::whereIn('group_id', $pastGroupIds)
            ->get()
            ->unique('user_id')
            ->pluck('user_id');

        return $pastEventAttendees->merge($pastGroupMembers);

    }
    public function callInviteFriends(array $friendIds, $resource, int $userId){

        SendInviteToFriends::dispatch( $friendIds, $resource, $userId);

    }
    /**
     * Invite friends
     *
     * @param array $request
     * @param Event $event
     *
     * @return Event
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function inviteFriends(array $friendIds, $resource, int $userId)
    {

        if (!empty($friendIds)) { // check if there's an id
            $userIds = [];

            if (get_class($resource) == 'App\Models\Event') {
                foreach ($friendIds as $id) {

                    $user = User::findOrFail($id);
                    $emailInvite = $this->updateEmailInvite($resource, $user);

                    // send an invite email to user
                    if ($emailInvite->sent_at == null) {
                        $userIds[] = $id;

                        // Check if valid to receive an email
                        if ($user->validTypeAccount) {
                            Mail::to($user->email)->send(new EventInvitation($user, $resource));
                        }
                    }

                    $this->tagSentInEmailInvite($resource, $user);
                }
            }

            if (get_class($resource) == 'App\Models\Group') {

                $groupInvites = self::generateGroupMemberInvites($resource, $friendIds);

                foreach ($groupInvites->new as $groupInvite) {
                    if ($groupInvite->user->validTypeAccount) {
                        Notification::send($groupInvite->user, new GroupInvitationReceivedNotification($groupInvite));
                    }

                }

                foreach ($friendIds as $id) {

                    $user = User::findOrFail($id);

                    $emailInvite = $this->updateEmailInvite($resource, $user);

                    $this->tagSentInEmailInvite($resource, $user);
                }

            }

            // Send notification for huddle
            if ($resource->gathering_type === GatheringType::HUDDLE) {
                $this->sendHuddleInvitation($resource, $userIds);
            }

        }
    }

    public function sendHuddleInvitation(Event $event, $userIds)
    {
        Notification::send(
            User::whereIn('id', $userIds)->get(),
            new HuddleInvitationNotification($event)
        );
    }

    protected function updateEmailInvite($event, $user)
    {

        $emailInvite = EmailInvite::updateOrCreate(
            ['past_resource' => 'App\Models\User', 'resource_id' => $event->id, 'user_id' => $user->id],
            ['past_resource' => 'App\Models\User', 'resource_id' => $event->id, 'user_id' => $user->id]
        );

        return $emailInvite;
    }

    protected function tagSentInEmailInvite($event, $user)
    {

        EmailInvite::updateOrCreate(
            ['past_resource' => 'App\Models\User', 'resource_id' => $event->id, 'user_id' => $user->id],
            ['past_resource' => 'App\Models\User', 'resource_id' => $event->id, 'user_id' => $user->id, 'sent_at' => Carbon::now()]
        );


    }

    /**
     * @param Event $event
     * @param array $pastEventIds
     *
     * @return null
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    protected static function insertInviteEmail($source, array $pastEventIds)
    {

        foreach ($pastEventIds as $pastEventId) {

            EmailInvite::updateOrCreate(
                [
                    'resource' => get_class($source),
                    'past_resource' =>  $pastEventId['type'] == 'event' ? 'App\Models\Event' : 'App\Models\Group',
                    'resource_id' =>  $source->id,
                    'past_resource_id' => $pastEventId['id'],
                ]
            );
        }
    }

    /**
     * @param Event $event
     *
     * @return null
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public static function sendInvite($source)
    {

        $resource = get_class($source);

        // get the past_events/huddles
        $emailEventInvite =   EmailInvite::where('past_resource', 'App\Models\Event')
            ->where('resource_id', $source->id)
            ->where('resource', $resource)
            ->whereNull('sent_at');

            // get the groups
        $emailGroupInvite =   EmailInvite::where('past_resource', 'App\Models\Group')
            ->where('resource_id', $source->id)
            ->where('resource', $resource)
            ->whereNull('sent_at');

        //get all the event/huddles/grops past_resource_id
        $emailEventInviteIds = $emailEventInvite->get('past_resource_id')->toArray();
        $emailGroupInviteIds = $emailGroupInvite->get('past_resource_id')->toArray();

        //get all the events/huddles user ids
        $pastEventAttendees =   UserEvent::with('user')
        ->whereIn('event_id', $emailEventInviteIds)
            ->where('user_id', "!=", $source->user_id)
            ->get()
            ->unique('user_id')
            ->pluck('user_id');

        //add Event Administrators to user array list
        if($resource == "App\Models\Event") {
            $pastEventAdmins = RoleUser::whereIn('resource_id', $emailEventInviteIds)
                ->whereResource($resource)
                ->get()
                ->unique('user_id')
                ->pluck('user_id');
            $pastEventAttendees = $pastEventAttendees->merge($pastEventAdmins)->unique();
        }

        //get all groups user ids
        $groupMembers =   UserGroup::with('user')->whereIn('group_id', $emailGroupInviteIds)
            ->where('user_id', "!=", $source->user_id)
            ->get()
            ->unique('user_id')
            ->pluck('user_id');

        // merge all the events/huddles/groups user ids
        $userIds = $pastEventAttendees->merge($groupMembers)->unique();

        $users = User::whereIn('id', $userIds)->get();

        if ($resource == 'App\Models\Event') {
            // select all the users
            foreach ($users as $user) {

                // Check if valid to receive an email
                Log::info($user->validTypeAccount);
                if ($user->validTypeAccount) {
                    Log::info('sendInvite');
                    Log::info($user);
                    $user->notify(new PastEventInvitationNotification($user, $source)); //send mail notification
                }
            }

        }

        if ($resource == 'App\Models\Group') {

            $groupInvites = self::generateGroupMemberInvites($source, $userIds->toArray());

            foreach ($groupInvites->new as $groupInvite) {

                Log::info($user->validTypeAccount);
                if ($groupInvite->user->validTypeAccount){
                    Log::info('sendInviteGroup');
                    Log::info($groupInvite->user);
                    Notification::send($groupInvite->user, new GroupInvitationReceivedNotification($groupInvite));
                }

            }

        }

        $emailEventInvite->update(['sent_at' => Carbon::now()]);
        $emailGroupInvite->update(['sent_at' => Carbon::now()]);

    }

    /**
     * @param Group $group
     * @param array $userIds
     * @return object
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function generateGroupMemberInvites(Group $group, array $userIds): object
    {
        $groupInvites = [
            'new' => [],
            'existing' => [],
            'members' => [],
        ];

        $alreadyMembers = $group->members()->whereIn('user_id', $userIds)->get();
        $existingInviteModels = $group->memberInvites()->whereIn('user_id', $userIds)->get();
        $newUserInvites = collect($userIds)
            ->filter(function (int $userId) use ($alreadyMembers, $existingInviteModels) {
                return !$existingInviteModels->pluck('user_id')->contains($userId)
                    && !$alreadyMembers->pluck('id')->contains($userId);
            });

        $groupInvites['new'] = $newUserInvites->map(function (int $userId) use ($group) {
            return self::inviteMemberToGroup($group, $userId);
        })->values();
        $groupInvites['existing'] = $existingInviteModels->values();
        $groupInvites['members'] = $alreadyMembers->values();

        return (object)$groupInvites;
    }

    /**
     * @param Group $group
     * @param int $userId
     * @return GroupMemberInvite
     *
     * @author Jay Aries Flores <aries@ragingriverict.com>
     */
    public function inviteMemberToGroup(Group $group, int $userId): GroupMemberInvite
    {
        return GroupMemberInvite::create([
            'user_id' => $userId,
            'group_id' => $group->id,
            'status' => GroupMemberInviteStatus::PENDING,
        ]);
    }

}
