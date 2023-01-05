<?php

namespace App\Http\Resources;

use App\Http\Resources\Media as MediaResource2;
use App\Http\Resources\UserSearchResource;
use App\Http\Resources\UserTalkResource;
use App\Models\Media;
use App\Models\User;
use App\Models\UserTalk;
use Illuminate\Http\Resources\Json\JsonResource;

class TalkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $userResource = new UserSearchResource(User::find($this->owner_id));
        return [
            "id" => $this->id,
            "title" => $this->title,
            "media_id" => $this->media_id,
            "description" => $this->description,
            "is_paid" => $this->is_paid,
            "amount" => $this->amount,
            "session_url" => $this->session_url,
            "is_private" => $this->is_private,
            "start" => $this->start,
            "duration" => $this->duration,
            "linked_entity" => $this->linked_entity,
            "linked_entity_id" => $this->linked_entity_id,
            "owner" => $userResource,
            "created_at" => $this->created_at,
            "updated_at" => $this->update_at,
            "stream_id" => $this->stream_id,
            "total_attendees" => UserTalk::whereTalkId($this->id)->whereStatus(UserTalk::STATUS_ACTIVE)->count(),
            "total_invited" => UserTalk::whereTalkId($this->id)->whereStatus(UserTalk::STATUS_INVITED)->count(),
            "media" => new MediaResource2(Media::find($this->media_id)),
            "attendees_preview" =>  UserTalkResource::collection(UserTalk::whereTalkId($this->id)->take(3)->get())
        ];
    }
}
