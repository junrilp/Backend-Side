<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InterestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function toArray($request)
    {
        $withMedia = [];
        if ($this->isWithMedia()) {
            $withMedia = [
                "media_path"  => getFileUrl($this->media->location),
            ];
        }

        return collect([
            "id"          => $this->id,
            "interest"    => $this->interest,
            "slug"        => $this->slug,
            "media_id"    => $this->media_id,
            "is_featured" => $this->is_featured
        ])->merge($withMedia);
    }

    /**
     * Automatic detect if resource found a media file
     *
     * @return bool
     * @author Angelito
     */
    public function isWithMedia() {
        return isset($this->media);
    }
}
