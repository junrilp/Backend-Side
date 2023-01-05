<?php

namespace App\Repository\Favorite;

interface FavoriteInterface
{

    public static function favoritedMe(int $userId);

    public static function myFavorites(int $userId);

}
