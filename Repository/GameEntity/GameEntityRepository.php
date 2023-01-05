<?php

namespace App\Repository\GameEntity;

use App\Enums\GameQuestionType as GameQuestionTypeEnums;
use App\Models\GameEntity;
use App\Models\GameQuestion;
use App\Models\GameQuestionType;

class GameEntityRepository implements GameEntityInterface
{
    public static function store($data)
    {
        return GameEntity::updateOrCreate([
            'name' => $data['name'],
            'description' => $data['description'],
            'duration' => $data['duration'],
            'game_id' => $data['game_id'],
            'host_id' => $data['host_id'],
            'game_question_type_id' => $data['game_question_type_id'] ?? null,
            'game_entity_category_id' => $data['game_entity_category_id'],
        ]);
    }

    public static function getById($data)
    {
        return GameEntity::find($data['game_entity_id']);
    }

    public static function delete(GameEntity $gameEntity)
    {
        return $gameEntity->delete();
    }

    public static function update(GameEntity $gameEntity, $data)
    {
        $gameEntity->name = $data['name'];
        $gameEntity->description = $data['description'];
        $gameEntity->duration = $data['duration'];
        $gameEntity->game_id = $data['game_id'];
        $gameEntity->host_id = $data['host_id'];
        $gameEntity->game_entity_category_id = $data['game_entity_category_id'];
        $gameEntity->save();

        return $gameEntity;
    }

    public static function filter($data)
    {
        $query = GameEntity::query();

        $query->when(request('game_entity_id', false), function ($q) use ($data) {
            return $q->where('game_id', $data['game_entity_id']);
        });

        // ->when() returns false when question_type is 0
        if (isset($data['question_type'])) {
            $query->where('game_question_type_id', 
                $data['question_type'] == 0 ? null : $data['question_type']);
        }

        $query->when(request('keyword', false), function ($q) use ($data) {
            return $q->where('name', 'LIKE', '%' . $data['keyword'] . '%');
        });

        return $query->get();
    }
}
