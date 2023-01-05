<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\DefaultPageType;

class UserSettingsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function toArray($request)
    {
        return [
            'id'      => $this->id ?? DefaultPageType::NO_SELECTION,
            'user_id' => $this->user_id ?? DefaultPageType::NO_SELECTION,
            'default' => $this->getDefaultPage($this->default_landing_page_type ?? DefaultPageType::NO_SELECTION),
            'default_landing_page' => $this->default_landing_page_type ?? DefaultPageType::NO_SELECTION,
            'show_welcome_msg'  => boolval ($this->show_welcome_msg ?? DefaultPageType::NO_SELECTION),
            'show_mobile_browse_msg'  => boolval ($this->show_mobile_browse_msg ?? DefaultPageType::NO_SELECTION),
            'show_mobile_events_msg'  => boolval ($this->show_mobile_events_msg ?? DefaultPageType::NO_SELECTION),
            'show_mobile_groups_msg'  => boolval ($this->show_mobile_groups_msg ?? DefaultPageType::NO_SELECTION),
        ];
    }

    /**
     * Get default page base on the current user settings
     *
     * @return string
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function getDefaultPage($page){
        $enums = collect(DefaultPageType::map())
                    ->get($page);
        return $enums['redirect'];
    }
}
