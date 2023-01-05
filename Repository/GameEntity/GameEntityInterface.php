<?php

namespace App\Repository\GameEntity;

use App\Models\GameEntity;

interface GameEntityInterface
{
    public static function store($data);

    public static function getById($data);

    public static function delete(GameEntity $gameEntity);

    public static function update(GameEntity $gameEntity, $data);

    public static function filter($data);
}
