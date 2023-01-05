<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Traits\DiscussionTrait;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscussionResource extends JsonResource
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

        $commentCount = $commentModel::where('discussion_id', $this->id)
            ->count();

        return [
            "id" => $this->id,
            "title" => $this->title,
            "discussion" => $this->discussion,
            'posted_by'     => new AuthUserResource(User::find($this->user_id)),
            'canModify'     => $canModify,
            'comments_count' => $commentCount,
            'isEdited'     => $this->updated_at != $this->created_at ? true : false,
            'comments' => CommentResource::collection($commentModel::where('discussion_id', $this->id)->where('entity_id', $this->entity_id)->where('parent_id', NULL)->orderBy('id', 'DESC')->limit(3)->get()),
            'attachments' => $this->whenLoaded('media', new MediasResource($this->media)),
            'is_owner' => $this->user_id === authUser()->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
