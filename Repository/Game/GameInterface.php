<?php

namespace App\Repository\Game;

use App\Models\Game;

interface GameInterface
{
    public static function store($data);

    public static function delete(Game $game);

    public static function update(Game $game, $data);

    public static function getAll();
}
