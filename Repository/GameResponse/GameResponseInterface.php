<?php

namespace App\Repository\GameResponse;

use App\Models\GameResponse;

interface GameResponseInterface
{
    public static function store($data);

    public static function delete(GameResponse $gameResponse);

    public static function update(GameResponse $gameResponse, $data);

    public static function show($gameResponse);
}
