<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'reported_user'     => $this->whenLoaded('reportedAccount', new UserBasicInfoResource2($this->reportedAccount)),
            'notes'             => $this->notes,
            'reporter_id'       => $this->reporter_id,
            'reporter'          => $this->whenLoaded('reportedBy', new UserBasicInfoResource2($this->reportedBy)),
            'type'              => $this->type,
            'attachment'        => $this->whenLoaded('attachment', MediaResource::collection($this->attachment)),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
