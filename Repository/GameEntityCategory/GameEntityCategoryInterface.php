<?php

namespace App\Repository\GameEntityCategory;

use App\Models\GameEntityCategory;

interface GameEntityCategoryInterface
{
    public static function store($data);

    public static function delete(GameEntityCategory $gameEntityCategory);

    public static function update(GameEntityCategory $gameEntityCategory, $data);

    public static function getAll();
}
