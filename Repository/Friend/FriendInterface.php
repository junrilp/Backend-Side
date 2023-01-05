<?php

namespace App\Repository\Friend;

interface FriendInterface
{

    public static function findFriend( int $userId, int $userId2, int $status = null);

    public static function getFriends( int $userId, string $status, int $perPage=12, bool $idOnly = false, bool $hasLimit = false);

    public static function requestFriend( int $userId, int $userId2 = null );

}
