<?php

namespace App\Repository\GameParticipant;

use App\Models\GameParticipant;

class GameParticipantRepository implements GameParticipantInterface
{
    public static function invite($data)
    {
        return GameParticipant::updateOrCreate([
            'game_entity_id' => $data['game_entity_id'],
            'user_id' => $data['user_id'],
        ]);
    }

    public static function delete(GameParticipant $gameParticipant)
    {
        return $gameParticipant->delete();
    }

    public static function update(GameParticipant $gameParticipant, $data)
    {
        $gameParticipant->game_entity_id = $data['game_entity_id'];
        $gameParticipant->user_id = $data['user_id'];
        $gameParticipant->status = $data['status'];
        $gameParticipant->has_answered = $data['has_answered'];
        $gameParticipant->save();

        return $gameParticipant;
    }

    public static function updateStatus(GameParticipant $gameParticipant, $data)
    {
        $gameParticipant->status = $data['status'];
        $gameParticipant->save();

        return $gameParticipant;
    }

    public static function getAll($data)
    {
//        dd($data, $data['game_entity_id']);
        return GameParticipant::where('game_entity_id', $data['game_entity_id'])
            ->get();
    }
}
