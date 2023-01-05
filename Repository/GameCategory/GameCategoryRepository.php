<?php

namespace App\Repository\GameCategory;

use App\Models\GameCategory;

class GameCategoryRepository implements GameCategoryInterface
{

    public static function store($data)
    {
        return GameCategory::updateOrCreate([
            'name' => $data['name'],
            'description' => $data['description'],
            'media_id' => $data['media_id'],
        ]);
    }

    public static function delete(GameCategory $gameCategory)
    {
        return $gameCategory->delete();
    }

    public static function update(GameCategory $gameCategory, $data)
    {
        $gameCategory->name = $data['name'];
        $gameCategory->description = $data['description'];
        $gameCategory->media_id = $data['media_id'];
        $gameCategory->save();

        return $gameCategory;
    }
}
