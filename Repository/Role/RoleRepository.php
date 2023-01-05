<?php

namespace App\Repository\Role;

use App\Enums\RoleType;
use App\Mail\AddedAsEventAdmin;
use App\Models\Event;
use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Gate;

class RoleRepository implements RoleInterface
{
    private $userId;
    private $eventId;


    public function __construct($userId, $eventId)
    {
        $this->userId = $userId;
        $this->eventId = $eventId;

        if (!authCheck()) {
            throw new \Exception('Unauthenticated', Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @param array $eventRoles
     * @param mixed $event
     * @param mixed $loginUserId
     *
     * @return [type]
     */
    public static function assignUserRole(array $resourceRoles = [], $resource, int $loginUserId)
    {
        // Will re-use this
        $query = RoleUser::query()
            ->where('resource_id', $resource->id)
            ->where('resource', get_class($resource));

        // get the current user from the role_user
        $roleUserLists = $query->pluck('user_id');

        // compare the request user lists vs the current lists from role_user
        $userToDelete = $roleUserLists->diff(collect($resourceRoles)->pluck('user_id'));

        // Remove what's left
        if (is_array($userToDelete->toArray())) {
            $query->where('user_id', '!=', authUser()->id);
            if (
                $resource->host->id === authUser()->id ||
                Gate::allows('can_update_roles', $resource)
            ) {
                $query->whereIn('user_id', $userToDelete->toArray())
                    ->delete();
            } elseif (Gate::allows('can_add_gatekeepers', $resource)) {
                $query->whereIn('user_id', $userToDelete->toArray())
                    ->where('role_id', 3) // Gatekeeper, temporarily static
                    ->delete();
            }
        }

        foreach ($resourceRoles as $role) {

            $user = User::findOrFail($role['user_id']);
            $role = Role::findOrfail($role['role_id']);

            $userRole = $user->assignRoleWithResource($role, $resource, $loginUserId);

            if (in_array($role->id, RoleType::keys())) {

                $start_date = Carbon::parse($resource->event_start);
                $end_date   = Carbon::parse($resource->event_end);
                $start_time = Carbon::parse($resource->start_time);
                $end_time   = Carbon::parse($resource->end_time);

                $details = [
                    'email_to_name'   => $user->first_name . ' ' . $user->last_name,
                    'email_from_name' => $resource->eventUser->first_name . ' ' . $resource->eventUser->last_name,
                    'title'           => class_basename($resource) == 'Event' ? $resource->title : $resource->name,
                    'event_start'     => $start_date->format('F j, Y') . ' at ' . $start_time->format('h:i a'),
                    'event_end'       => $end_date->format('F j, Y') . ' at ' . $end_time->format('h:i a'),
                    'status'          => 'invited to be a',
                    'role'            => $role->label,
                    'link'            => class_basename($resource)=='Event' ? url("/events/{$resource->slug}") : url("/groups/{$resource->slug}"),
                    'abilities'       => class_basename($resource) == 'Event' ? self::getUserAbilities($user->id, $resource->id) : ['Edit Group'],
                    'subject'         => $role->label . ' Invitation',
                    'copy_year'       => env('MAIL_COPY_YEAR') ? env('MAIL_COPY_YEAR') : date('Y'),
                    'mail_from'       => env('MAIL_FROM') ? env('MAIL_FROM') : 'support@perfectfriend.com',
                    'type'            => class_basename($resource)
                ];

                // For newly created user with roles, send an email, check if is_case is valid to received an email
                if ($userRole->wasRecentlyCreated && $user->validTypeAccount) {
                    Mail::to($user)
                        ->queue(new AddedAsEventAdmin($details));
                }
            }
        }

        return true;
    }


    public function canScanQr()
    {
        return $this->getAbilities()->contains('can_scan_qr');
    }

    public function getAbilities()
    {
        return User::find($this->userId)
                        ->eventRoles($this->eventId)
                        ->get()
                        ->map
                        ->abilities
                        ->flatten()
                        ->pluck('name')
                        ->unique();
    }

    public static function getUserAbilities($userId, $eventId)
    {
        return User::find($userId)
                        ->eventRoles($eventId)
                        ->get()
                        ->map
                        ->abilities
                        ->flatten()
                        ->pluck('label');
    }

    public static function getGroupUserAbilities($userId, $groupId)
    {
        return User::find($userId)
            ->groupRoles($groupId)
            ->get()
            ->map
            ->abilities
            ->flatten()
            ->pluck('label');
    }
}
