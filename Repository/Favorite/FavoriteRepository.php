<?php

namespace App\Repository\Favorite;


use App\Models\User;


class FavoriteRepository implements FavoriteInterface
{

    /**
     * @param mixed $userId
     *
     * @return Model User->favoritedMe
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public static function favoritedMe($userId, $idOnly = false) {

        $favoritedMe =  User::with('interests')->find($userId)->favoritedMe()->simplePaginate(12);

        if ($idOnly) {

            $favoritedMeIds =  User::with('interests')->find($userId)->favoritedMe()->get()->pluck('id')->toArray();

            return $favoritedMeIds;

        }

        return $favoritedMe;

    }

    /**
     * @param mixed $userId
     *
     * @return Model User->myFavorites
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public static function myFavorites($userId,  $idOnly = false)
    {

        $myFavorites =  User::with('interests')->find($userId)->favoritedTo()->simplePaginate(12);

        if ($idOnly) {

            $myFavoritesIds =  User::with('interests')->find($userId)->favoritedTo()->get()->pluck('id')->toArray();

            return $myFavoritesIds;

        }

        return $myFavorites;

    }



}
