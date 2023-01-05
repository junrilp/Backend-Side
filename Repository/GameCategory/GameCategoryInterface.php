<?php

namespace App\Repository\GameCategory;

use App\Models\GameCategory;

interface GameCategoryInterface
{
    public static function store($data);

    public static function delete(GameCategory $gameCategory);

    public static function update(GameCategory $gameCategory, $data);
}
