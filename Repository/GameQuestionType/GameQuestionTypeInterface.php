<?php

namespace App\Repository\GameQuestionType;

use App\Models\GameQuestionType;

interface GameQuestionTypeInterface
{
    public static function store($data);

    public static function delete(GameQuestionType $gameQuestionType);

    public static function update(GameQuestionType $gameQuestionType, $data);
}
