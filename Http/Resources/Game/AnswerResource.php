<?php

namespace App\Http\Resources\Game;

use App\Http\Resources\UserResource2;
use App\Models\Games\WYR\Answer;
use App\Models\Games\WYR\WYR;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;

class AnswerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array|Arrayable|JsonSerializable
     */
    public function toArray($request)
    {
        $wyr = WYR::find($this->wyr_id);

        return [
            'id' => $this->id,
            'wyr' => new WYRResource([
                'wyr' => $wyr,
                'user' => User::find($wyr->host)
            ]),
            'is_approved' => $this->is_approved,
            'submitted_by' => $this->submitted_by,
            'answer' => $this->choice,
            'answer_set' => Answer::select('id', 'wyr_id', 'choice')
                ->where('answer_set_id', $this->answer_set_id)
                ->get(),
        ];
    }
}
