<?php

namespace App\Repository\GameChoice;

use App\Models\GameChoice;

class GameChoiceRepository implements GameChoiceInterface
{
    public static function store($data)
    {
        return GameChoice::updateOrCreate([
            'value' => $data['value'] ?? null,
            'question_id' => $data['question_id'],
            'game_entity_id' => $data['game_entity_id'],
            'media_id' => $data['media_id'] ?? null,
        ]);
    }

    public static function delete(GameChoice $gameChoice)
    {
        return $gameChoice->delete();
    }

    public static function update(GameChoice $gameChoice, $data)
    {
        $gameChoice->value = $data['value'];
        $gameChoice->question_id = $data['question_id'];
        $gameChoice->game_entity_id = $data['game_entity_id'];
        $gameChoice->media_id = $data['media_id'];
        $gameChoice->save();

        return $gameChoice;
    }
}
