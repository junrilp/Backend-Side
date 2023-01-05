<?php

namespace App\Repository\GameQuestion;

use App\Models\GameQuestion;
use phpseclib3\Math\PrimeField\Integer;

interface GameQuestionInterface
{
    public static function store($data);

    public static function delete(GameQuestion $gameQuestion);

    public static function update(GameQuestion $gameQuestion, $data);

    public static function show($gameQuestion);

    public static function getQuestionsByEntityId($entityId);
}
