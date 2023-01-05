<?php

namespace App\Repository\Album;

interface AlbumInterface
{
    public static function getAlbums(string $type, int $eventId, int $perPage);

    public static function getAlbumById(string $type, int $albumId);

    public static function getAlbumsItemsById(string $type, int $albumId, int $perPage);

    public static function storeAlbum(string $type, int $eventId, int $userId, array $data = []);

    public static function updateAlbum(string $type, int $eventId, int $userId, array $data = [], int $albumId);

    public static function destroyAlbum(string $type, int $id);

    public static function destroyAlbumAttachment(string $type, int $eventAlbumId, int $id);

    public static function storeAlbumItems(string $type, int $eventAlbumsId, array $data = [], int $userId);

    public static function destroyAlbumAttachmentByMediaId(string $type, int $eventAlbumId, int $mediaId);
}
