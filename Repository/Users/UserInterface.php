<?php

namespace App\Repository\Users;

interface UserInterface
{

    public static function getUsers($userId);

    public static function loginAccount(
        string $userName,
        string $password,
        bool $remember
    );

    public static function getMedia($id);

    public static function getUserById(int $userId);

    public static function validateAccount(
        string $token,
        int $status,
        string $emailVerifiedAt = null
    );

    public static function getActiveUsersCount();

    public static function getUserWallVideos($wallAttachmentIds);

    public static function getEventWallVideos();

    public static function getGroupWallVideos();
}
