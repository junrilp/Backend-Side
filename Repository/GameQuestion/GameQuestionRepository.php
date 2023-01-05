<?php

namespace App\Repository\GameQuestion;

use App\Enums\GameQuestionStatus;
use App\Models\GameQuestion;

class GameQuestionRepository implements GameQuestionInterface
{
    public static function store($data)
    {
        return GameQuestion::updateOrCreate([
            'value' => $data['value'] ?? null,
            'game_entity_id' => $data['game_entity_id'],
            'user_id' => $data['user'],
            'game_question_type_id' => $data['game_question_type_id'],
            'media_id' => $data['media_id'] ?? null,
            'status' => $data['status'],
        ]);
    }

    public static function delete(GameQuestion $gameQuestion)
    {
        return $gameQuestion->delete();
    }

    public static function update(GameQuestion $gameQuestion, $data)
    {
        $gameQuestion->value = $data['value'];
        $gameQuestion->game_entity_id = $data['game_entity_id'];
        $gameQuestion->user_id = $data['user'];
        $gameQuestion->game_question_type_id = $data['game_question_type_id'];
        $gameQuestion->media_id = isset($data['media_id']) ? $data['media_id'] : $gameQuestion->media_id;
        $gameQuestion->status = $data['status'];
        $gameQuestion->save();

        return $gameQuestion;
    }

    public static function show($gameQuestion)
    {
        return GameQuestion::find($gameQuestion);
    }

    public static function getQuestionsByEntityId($entityId)
    {
        return GameQuestion::where('game_entity_id', $entityId)
            ->get();
    }
}
