<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Traits\DiscussionTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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

        $canModify = false;
        if (isset($request->user()->id)) {
            $canModify = ($this->user_id == $request->user()->id);
        }

        $commentCount = $commentModel::where('parent_id', $this->id)
            ->count();

        return [
            'id'            => $this->id,
            'discussion_id' => $this->discussion_id,
            'comment'       => $this->comment,
            'posted_by'     => new AuthUserResource(User::find($this->user_id)),
            'canModify'     => $canModify,
            'isEdited'     => $this->updated_at != $this->created_at ? true : false,
            'parentId'      => $this->parent_id,
            'comment_count' => $commentCount,
            'attachments' => $this->whenLoaded('media', new MediasResource($this->media)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
