<?php

namespace App\Repository\Media;

use Illuminate\Http\UploadedFile;

interface MediaInterface
{
    public static function checkAndSaveImage(
        UploadedFile $file,
        UploadedFile $image_full = null
    );
    public static function updateMedia(
        int $mediaId,
        int $userId
    );

    public static function getImageURLById(int $mediaId);

    public static function getAdditionalPhotosByUserId(int $userId);

    public static function deleteSingleImage(int $id);
}
