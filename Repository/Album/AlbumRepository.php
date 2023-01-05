<?php

namespace App\Repository\Album;

use App\Models\EventAlbum;
use App\Traits\AlbumTraits;
use Illuminate\Support\Arr;
use App\Traits\ApiResponser;
use App\Enums\DiscussionType;
use App\Models\EventAlbumItem;
use App\Models\GroupAlbumItem;
use Illuminate\Support\Facades\DB;

class AlbumRepository implements AlbumInterface
{
    use ApiResponser, AlbumTraits;
    
    /**
     * Retrieve all albums by event
     * 
     * @param int $eventId
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getAlbums(string $type, int $eventId, int $perPage)
    {
        $getModel = AlbumTraits::getAlbumTrait($type, $eventId);
        return $getModel->with('resourceEntity')->paginate($perPage);
    }


    /**
     * @param int $albumId
     * 
     * @author John Dometita <john.d@ragingriverict.com>
     */
    public static function getAlbumById(string $type, int $albumId)
    {
        $getModel = AlbumTraits::getAlbumById($type);
        return $getModel::with(['visualFiles','resourceEntity'])->find($albumId);
    }


    /**
     * Retrieve all items on specific album by album Id
     * 
     * @param int $eventId
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getAlbumsItemsById(string $type, int $albumId, int $perPage)
    {
        $getModel = AlbumTraits::getAlbumsItemsById($type, $albumId);
        return $getModel->with('media')
                ->paginate($perPage);
    }

    /**
     * Store new album
     * 
     * @param int $eventId
     * @param int $userId
     * @param array $request
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function storeAlbum(string $type, int $eventId, int $userId, array $data = [])
    {
        return self::processForm($type, $eventId, $userId, $data);
    }

    /**
     * Update album 
     * 
     * @param int $eventId
     * @param int $userId
     * @param array $request
     * @param int $albumId
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateAlbum(string $type, int $eventId, int $userId, array $data = [], int $albumId)
    {
        return self::processForm($type, $eventId, $userId, $data, $albumId);
    }

    /**
     * Remove Album
     * 
     * @param int $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function destroyAlbum(string $type, int $id)
    {
        $getModel = AlbumTraits::getAlbumById($type);
        
        return $getModel::find($id)->delete();
    }

    /**
     * Remove specific album items
     * 
     * @param int $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function destroyAlbumAttachment(string $type, int $albumId, int $id)
    {
        $getModel = AlbumTraits::getAlbumsItemsById($type, $albumId);
        return $getModel->find($id)->delete();
    }

    /**
     * Remove specific album items
     * 
     * @param int $id
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function destroyAlbumAttachmentByMediaId(string $type, int $eventAlbumId, int $mediaId)
    {
        $getModel = AlbumTraits::destroyAlbumAttachmentByMediaIdTrait($type, $eventAlbumId);

        $albumItem = $getModel->where('media_id', $mediaId);

        if ($albumItem->exists()) {
            return $albumItem->first()->delete();
        }
        
        return true;
    }

    /**
     * Will create a private function for our album form
     * This method will used both on storing and updating
     * the values on our event_albums and event_album_items
     * 
     * @param int $eventId
     * @param int $userId
     * @param array $request
     * @param int|null $albumId
     * 
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    private static function processForm(string $type, int $eventId, int $userId, array $data = [], int $albumId = null)
    {
        
        $result = DB::transaction(function() use ($type, $eventId, $userId, $data, $albumId) {
            
            // Will get the Model to be used on saving or updating the record EventAlbum::class or GroupAlbum::class
            $getModel = AlbumTraits::getAlbumById($type);
            $forms = [];
            $formItems = [];

            // Will check if the form is from events page
            if ($type === DiscussionType::EVENTS) {

                // Will initialize our form intended for event_album only
                $forms = [
                    'name' => $data['name'],
                    'event_id' => $eventId,
                    'user_id' => $userId
                ];

                // Will initialize our form items intended for event_album_items only
                $formItems = [
                    'event_albums_id' => $albumId,
                    'user_id' => $userId
                ];
            }

            // Will check if the form is from group page
            if ($type === DiscussionType::GROUPS) {

                // Will initialize our form intended for group_album only
                $forms = [
                    'name' => $data['name'],
                    'group_id' => $eventId,
                    'user_id' => $userId
                ];

                // Will initialize our form items intended for group_album_items only
                $formItems = [
                    'group_albums_id' => $albumId,
                    'user_id' => $userId
                ];
            }

            // Saved or Updating the records on event_album or group_album
            $album = $getModel::updateOrCreate([
                        'id' => $albumId
                    ], $forms );

            if (empty($albumId)) {
                $albumId = $album->id;
            }

            if (isset($data['media_id'])) {

                // Will get the Model to be used on saving or updating the record EventAlbumItem::class or GroupAlbumItem::class
                $getModel = AlbumTraits::addAlbumItems($type);

                // Will check if the media is in array
                if (is_array($data['media_id'])) {
                    // if its an array will extract it
                    foreach ($data['media_id'] as $row) {
                        $getModel::updateOrCreate([
                            $formItems,
                            'media_id' => $row
                        ]);
                    }
                } else {
                    // to prevent returning an error if its not an array
                    // will catch the object and store it
                    $getModel::updateOrCreate([
                        $formItems,
                        'media_id' => $data['media_id'],
                    ]);
                }
            }
            
            $album->load('visualFiles');

            return $album;

        });

        return $result;
    }

    /**
     * Store Items into specific album
     * 
     * @param int $entityAlbumsId
     * @param array $data
     * 
     * @return [type]
     */
    public static function storeAlbumItems(string $type, int $entityAlbumsId, array $data = [], int $userId)
    {

        if ($type === DiscussionType::EVENTS) {
            return self::storeALbumItemsEvent($entityAlbumsId, $data, $userId);
        }

        if ($type === DiscussionType::GROUPS) {
            return self::storeALbumItemsGroup($entityAlbumsId, $data, $userId);
        }
        
        
    }

    private static function storeALbumItemsEvent(int $entityAlbumsId, array $data = [], int $userId)
    {
        $files = [];
        if (is_array($data['media_id'])) {
            foreach ($data['media_id'] as $mediaId) {
                $files[] = EventAlbumItem::updateOrCreate(
                    [
                        'event_albums_id' => $entityAlbumsId,
                        'media_id' => $mediaId,
                    ], [
                    'event_albums_id' => $entityAlbumsId,
                    'media_id' => $mediaId,
                    'user_id' => $userId
                ]);
            }
        } 

        return $files;
    }

    private static function storeALbumItemsGroup(int $entityAlbumsId, array $data = [], int $userId)
    {
        $files = [];
        if (is_array($data['media_id'])) {
            foreach ($data['media_id'] as $mediaId) {
                $files[] = GroupAlbumItem::updateOrCreate(
                    [
                        'group_albums_id' => $entityAlbumsId,
                        'media_id' => $mediaId,
                    ], [
                    'group_albums_id' => $entityAlbumsId,
                    'media_id' => $mediaId,
                    'user_id' => $userId
                ]);
            }
        } 

        return $files;
    }
}
