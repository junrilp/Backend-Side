<?php

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Carbon;

/**
 * @property Group $resource
 */
class PastGroupResource extends JsonResource
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
            "past_type" => 'group',
            "title" => $this->name,
            "rsvpd_users_count" => count($this->members)==1 ? 0 : count($this->members) - 1, // return 0 if only 1 member which is the owner otherwise return member excluding owner (-1)
            "attendees_count" => count($this->members) == 1 ? 0 : count($this->members) - 1,
            "slug" => $this->slug,
        ];
    }

}
