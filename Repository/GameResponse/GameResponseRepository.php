<?php

namespace App\Repository\GameResponse;

use App\Models\GameResponse;

class GameResponseRepository implements GameResponseInterface
{
    public static function store($data)
    {
        // Tried $data['user_id'] ? $data['user_id'] : authUser()->id,
        if (isset($data['user_id'])) {
            $userId = $data['user_id'];
        } else {
            $userId = authUser()->id;
        }

        return GameResponse::updateOrCreate([
            'game_entity_id' => $data['game_entity_id'],
            'question_id' => $data['question_id'],
            'choice_id' => $data['choices_id'],
            'user_id' => $userId
        ]);
    }

    public static function delete(GameResponse $gameResponse)
    {
        return $gameResponse->delete();
    }

    public static function update(GameResponse $gameResponse, $data)
    {
        $gameResponse->game_entity_id = $data['game_entity_id'];
        $gameResponse->question_id = $data['question_id'];
        $gameResponse->choice_id = $data['choices_id'];
        $gameResponse->save();

        return $gameResponse;
    }

    public static function show($gameResponse)
    {
        return GameResponse::find($gameResponse);
    }

    public static function getQuestionsByEntityId($entityId)
    {
        return GameResponse::where('game_entity_id', $entityId)
            ->get();
    }
}
