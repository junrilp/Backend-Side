<?php

namespace App\Http\Resources;


use App\Models\Event;
use App\Models\RoleUser;
use App\Models\Group;
use Illuminate\Http\Resources\Json\JsonResource;



class PastInviteResource extends JsonResource
{

    protected $authUserId;

    public function setAuthUserId(int $userId)
    {
        $this->authUserId = $userId;
        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "past_type" => $this->past_type,
            "title" => $this->title,
            "rsvpd_users_count" => $this->getCount($this->past_type, $this->id, $this->user_id), // return 0 if only 1 member which is the owner otherwise return member excluding
            // "rsvpd_users_count" => count($this->members)==1 ? 0 : count($this->members) - 1, // return 0 if only 1 member which is the owner otherwise return member excluding
            "slug" => $this->slug,
        ];
    }

    public function getCount($type, $id, $eventOwner) {
        if ($type=='group') {
            $groupMembers = Group::withCount(["members" => function($query) use ($eventOwner) {
                $query->where('user_id', '<>', $eventOwner);
            }])->find($id);

            return $groupMembers->members_count;
        }

        if ($type=='event') {
            $eventMembers = Event::withCount(["attendees" => function($query) use ($eventOwner) {
                $query->where('user_id', '<>', $eventOwner);
            }])->find($id);

            $eventAdmin = RoleUser::whereResourceId($id)->where('resource', 'like', '%Event')->get();

            $membersAndAdmin = $eventMembers->attendees_count + $eventAdmin->count();

            return $membersAndAdmin;
        }
        return 0;
    }


}
