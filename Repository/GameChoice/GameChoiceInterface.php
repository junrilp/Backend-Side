<?php

namespace App\Repository\GameChoice;

use App\Models\GameChoice;

interface GameChoiceInterface
{
    public static function store($data);

    public static function delete(GameChoice $gameChoice);

    public static function update(GameChoice $gameChoice, $data);
}
