<?php

namespace App\Http\Resources;

use App\Models\Event;
use App\Models\User;
use App\Models\Media;
use App\Models\EventWallDiscussionLike;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Traits\DiscussionTrait;

class EventWallResource extends JsonResource
{
    use DiscussionTrait;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $url = $request->segment(2);
        $commentModel = null;

        $commentModel = DiscussionTrait::getCommentTrait($url);

        if ($commentModel == null) {
            return false;
        }

        $media = Media::find($this->media_id);

        return [
            'id' => $this->id,
            'body' => $this->body,
            'description' => $this->description ?? '',
            'type' => $this->type ?? 'wall',
            'media_id' => $this->media_id,
            'media_type' => $this->media_type,
            'primary_attachment' => new PostAttachmentResource(Media::find($this->media_id)),
            'user' => new UserBasicInfoResource2(User::findOrFail($this->user_id)),
            'other_attachment' => $this->whenLoaded('wallAttachment', !empty($this->wallAttachment) ? PostAttachmentResource::collection($this->wallAttachment) : ''),
            'attachments' => WallAttachmentResource::collection($this->whenLoaded('attachments')),
            'is_owner' => authUser() ? ($this->user_id == authUser()->id ? true : false) : false,
            'is_liked' => $this->alreadyLiked,
            'total_likes' => $this->likesCount,
            'is_transcoding' => $this->whenLoaded(
                'attachments', 
                $this->attachments && 
                $this->attachments->contains(function ($attachment) {
                    return (int) $attachment->media->processing_status === 1;
                })
            ),
            'people_like' => UserBasicInfoResource::collection($this->recentLikers),
            'event_owner' => Event::whereId($this->entity_id)->where('user_id', authUser()->id)->exists(),
            'comments' => CommentResource::collection($commentModel::where('discussion_id', $this->id)->where('entity_id', $this->entity_id)->where('parent_id', NULL)->orderBy('id', 'DESC')->limit(3)->get()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
