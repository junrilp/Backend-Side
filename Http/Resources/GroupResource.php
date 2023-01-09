<?php

namespace App\Http\Resources;

use App\Enums\GeneralStatus;
use App\Models\Group;
use App\Models\GroupMemberInvite;
use App\Models\User;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Carbon;

/**
 * @property Group $resource
 */
class GroupResource extends JsonResource
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
            "name" => $this->name,
            "title" => $this->name,
            "slug" => $this->slug,
            "description" => $this->description,
            "total_members" => $this->total_members,
            "image_url" => $this->getMediaUrl(),
            "image_modified_url" => $this->whenLoaded('media', new Media($this->media)),
            "video_url" => $this->getVideoUrl(),
            "video_id" => $this->getVideoId(),
            "video_is_transcoding" => $this->video->is_transcoding ?? false,
            "user" => new UserBasicInfoResource(User::withoutGlobalScopes()->withTrashed()->findOrFail($this->user_id)),
            "type" => new TypeResource($this->whenLoaded('type')),
            "category" => new CategoryResource($this->whenLoaded('category')),
            "tags" => TagResource::collection($this->whenLoaded('tags')),
            "is_pf" => $this->is_pf,
            "group_tye" => $this->group_type,
            "members" => $this->relationLoaded('members') ? UserBasicInfoResource::collection($this->whenLoaded('members')->take(3)) : null,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            'can_edit' => $this->can_edit,
            "is_user_owner" => $this->user_id !== null && $this->authUserId === $this->user_id,
            "is_user_member" => authUser() ? $this->is_user_member : false,
            "is_published" => $this->isPublished(),
            "published_date" => $this->published_at ? Carbon::parse($this->published_at)->toDateTimeString() : null,
            "is_chat_enabled" => boolval($this->live_chat_enabled),
            "is_exclusive" => $this->exclusive,
            "albums_count" => $this->albums_count,
            "photos_count" => $this->photos_count,
            "videos_count" => $this->videos_count,
            "deleted_at" => $this->deleted_at,
            "status" => $this->status !== NULL ? GeneralStatus::map()[$this->status-1]['value'] : NULL,
            "interests" => $this->interests ? new InterestsResource($this->interests) : [],
            "admins" => new GroupAdminResource($this) ?? null,
            "invites" => $this->relationLoaded('memberInvites') ? GroupMemberInviteResource::collection($this->whenLoaded('memberInvites')->each(function (GroupMemberInvite $memberInvite) {
                $memberInvite->load('user');
            })) : null,
            "user_notes" => $this->relationLoaded('groupNotes') ? NoteResource::collection($this->groupNotes) : null,
        ];
    }

    /**
     * @return string|null
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    private function getMediaUrl(): ?string
    {
        $image = $this->whenLoaded('media');
        return getFileUrl(optional($image)->location);
    }

    /**
     * @return string|null
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    // @TODO remove this after SPRINT-202
    private function getModifiedMediaUrl(): ?string
    {
        $image = $this->whenLoaded('media');
        return $image && !($image instanceof MissingValue) ? optional($image)->getModifiedMediaUrl() : null;
    }

    /**
     * Get the video url
     *
     * @return string|null
     */
    private function getVideoUrl(): ?string
    {
        $video = $this->whenLoaded('video');
        return getFileUrl(optional($video)->location);
    }

    private function getVideoId()
    {
        $video = $this->whenLoaded('video');
        return $video->id ?? null;
    }

    public static function collection($resource)
    {
        return new GroupResourceCollection($resource);
    }
}
