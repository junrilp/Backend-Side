<?php

namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class Media extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        $locationParts = explode('.', $this->location);
        $extension = array_pop($locationParts);
        if ($this->modification_suffix) {
            $modified = implode('.', $locationParts).$this->modification_suffix.'.'.$extension;
        }
        $origUrl = getFileUrl($this->location);
        $modifiedUrl = isset($modified) ? getFileUrl($modified) : getFileUrl($this->location);

        $thumbnailUrl = str_replace('photos/','thumbnail/', $modifiedUrl);
        $smUrl = str_replace('photos/','small_size/', $modifiedUrl);
        $mdUrl = str_replace('photos/','medium_size/', $modifiedUrl);
        $lgUrl = str_replace('photos/','fullwidth_size/', $modifiedUrl);

        return [
            'id' => $this->id,
            "media_type_id"=> $this->media_type_id,
            'original' => $origUrl,
            'modified' => $modifiedUrl,
            'sizes' => [
                'thumbnail' => $thumbnailUrl,
                'sm' => $smUrl,
                'md' => $mdUrl,
                'lg' => $lgUrl
            ],
            'blurhash' => [
                'hash' => $this->blurhash,
                'width' => $this->width,
                'height' => $this->height,
            ],
            'client' => $this->name,
            'created_at' => $this->created_at,
            'is_transcoding' => $this->is_transcoding
        ];
    }
}
