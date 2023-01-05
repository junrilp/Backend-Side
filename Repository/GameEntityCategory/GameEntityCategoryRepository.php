<?php

namespace App\Repository\GameEntityCategory;

use App\Models\GameEntityCategory;

class GameEntityCategoryRepository implements GameEntityCategoryInterface
{

    public static function store($data)
    {
        return GameEntityCategory::updateOrCreate([
            'name' => $data['name'],
            'description' => $data['description'],
            'media_id' => $data['media_id'],
        ]);
    }

    public static function delete(GameEntityCategory $gameEntityCategory)
    {
        return $gameEntityCategory->delete();
    }

    public static function update(GameEntityCategory $gameEntityCategory, $data)
    {
        $gameEntityCategory->name = $data['name'];
        $gameEntityCategory->description = $data['description'];
        $gameEntityCategory->media_id = $data['media_id'];
        $gameEntityCategory->save();

        return $gameEntityCategory;
    }

    public static function getAll()
    {
        return GameEntityCategory::get();
    }
}
