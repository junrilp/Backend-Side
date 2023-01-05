<?php

namespace App\Repository\GameQuestionType;

use App\Models\GameQuestionType;

class GameQuestionTypeRepository implements GameQuestionTypeInterface
{
    public static function store($data)
    {
        return GameQuestionType::updateOrCreate([
            'name' => $data['name'],
        ]);
    }

    public static function delete(GameQuestionType $gameQuestionType)
    {
        return $gameQuestionType->delete();
    }

    public static function update(GameQuestionType $gameQuestionType, $data)
    {
        $gameQuestionType->name = $data['name'];
        $gameQuestionType->save();

        return $gameQuestionType;
    }
}
