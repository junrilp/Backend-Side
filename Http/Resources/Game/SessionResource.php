<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\UserResource2;
use App\Models\Games\Session;
use App\Models\Games\WYR\WYR;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class SessionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $allParticipantsIds = Session::where('game_entity_id', $this->id)
            ->withTrashed()
            ->get();

        $allParticipants = User::whereIn('id', $allParticipantsIds->pluck('user_id'))
            ->get();

        $activeParticipantsIds = Session::where('game_entity_id', $this->id)
            ->whereNull('deleted_at')
            ->get();

        $activeParticipants = User::whereIn('id', $activeParticipantsIds->pluck('user_id'))
            ->get();

        return [
            'wyr' => new WYRResource([
                'wyr' => $this,
                'user' => $this->owner
            ]),
            'is_popular' => $this->is_popular,
            'all_participants' => UserResource2::collection($allParticipants),
            'active_participants' => UserResource2::collection($activeParticipants),
        ];
    }
}
