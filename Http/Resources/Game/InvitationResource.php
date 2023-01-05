<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\UserResource2;
use App\Models\Games\WYR\WYR;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class InvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $wyr = WYR::find($this->session_id);
        $sender = User::find($this->sender_id);
        $recipient = User::find($this->recipient_id);

        return [
            'invitation_id' => $this->id,
            'wyr' => new WYRResource([
                'wyr' => $wyr,
                'user' => $wyr->owner,
            ]),
            'sender' => new UserResource2($sender),
            'recipient' => new UserResource2($recipient),
        ];
    }
}
