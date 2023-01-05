<?php

namespace App\Repository\Game;

use App\Models\Game;

class GameRepository implements GameInterface
{
    public static function store($data)
    {
        return Game::updateOrCreate([
            'name' => $data['name'],
            'category_id' => $data['category_id'],
        ]);
    }

    public static function delete(Game $game)
    {
        return $game->delete();
    }

    public static function update(Game $game, $data)
    {
        $game->name = $data['name'];
        $game->category_id = $data['category_id'];
        $game->save();

        return $game;
    }

    public static function getAll()
    {
        return Game::get();
    }
}
