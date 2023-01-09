<?php

namespace App\Repository\Media;

use Exception;
use App\Models\Media;
use App\Enums\MediaTypes;
use Illuminate\Support\Str;
use App\Models\AdditionalPhotos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;
use kornrunner\Blurhash\Blurhash;
use Image;

class MediaRepository implements MediaInterface
{

    /**
     * @param UploadedFile $file
     * @param UploadedFile|null $image_full
     *
     * @return [type]
     */
    public static function checkAndSaveImage(
        UploadedFile $file,
        UploadedFile $image_full = null
    ) {
        $isUploadedFile = $file instanceof UploadedFile;
        if ($isUploadedFile) {
            $fileExtension = strtolower($file->getClientOriginalExtension());
        } else {
            // Get the fileExtension so we can make sure we support the image type.
            $fileExtension = getFileExtensionFromBase64($file);
        }

        if (in_array($fileExtension, ['flv', 'mp4', 'm3u8', 'ts', '3gp', 'mov', 'avi', 'wmv'])) {
            $mediaType = MediaTypes::VIDEOS;
        } else if (in_array($fileExtension, ['jpg', 'png', 'jpeg', 'gif', 'svg', 'webp', 'heic'])) {
            $mediaType = MediaTypes::PHOTOS;
        } else {
            Log::info($fileExtension);
            return false;
        }
        if ($isUploadedFile) {
            $media = self::addMedia($file, $image_full, $mediaType, Auth::user()->id ?? null, null ?? 0);
        }

        if (!$media) {
            return false;
        }
        return $media;
    }

    /**
     * @param UploadedFile $file
     * @param UploadedFile|null $image_full
     * @param int $mediaType
     * @param int|null $userId
     * @param int|null $postId
     *
     * @return [type]
     */
    public static function addMedia(
        UploadedFile $file,
        UploadedFile $image_full = null,
        int $mediaType,
        int $userId = null,
        int $postId = null
    ) {
        try {

            $originalName = $file->getClientOriginalName();

            if ($file) {

                $randomImageName = Str::random('12') . round(microtime(true) * 1000);
                $filename = $randomImageName . "." . $file->getClientOriginalExtension();

                Storage::disk(env('FILESYSTEM_DRIVER'))->put($filename, file_get_contents($file));

            }

            if (!$file) {
                return false;
            }

            if ($image_full) {
                // Save original image for reference
                $filenameFull = $randomImageName . "_fi." . $image_full->getClientOriginalExtension();

                Storage::disk(env('FILESYSTEM_DRIVER'))->put($filenameFull, file_get_contents($image_full));
            }

            return Media::create([
                'user_id'         => $userId,
                'media_type_id'   => $mediaType,
                'location'        => $filename,
                'name'            => $originalName,
            ]);

        } catch (Exception $e) {
            Log::critical("File upload Error Message: " . $e->getMessage());
            return $e;
            return false;
        }
    }

    /**
     * @param int $mediaId
     * @param int $userId
     *
     * @return [type]
     */
    public static function updateMedia(
        int $mediaId,
        int $userId
    ) {
        Media::whereId($mediaId)
            ->update([
                'user_id' => $userId
            ]);
        return true;
    }

    /**
     * Will get the image by image_id or location
     * @param int $mediaId
     *
     * @return object
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function getImageURLById(int $mediaId)
    {
        $string = Media::whereId($mediaId)->first();
        if ($string) {
            $string = $string->location;
            return getFileUrl($string);
        }
        return "";
    }

    /**
     * @param int $userId
     *
     * @return [type]
     */
    public static function getAdditionalPhotosByUserId(int $userId)
    {
        $images = AdditionalPhotos::where('user_id', $userId)
            ->get();

        $data = [];
        foreach ($images as $photo) {
            $data[] = [
                'id' => $photo->id,
                'image' => self::getImageURLById($photo->image)
            ];
        }
        return $data;
    }

    /**
     * @param mixed $mediaId
     *
     * @return [type]
     */
    public static function unlinkMedia($mediaId)
    {
        $getMedia = Media::whereId($mediaId)
            ->first();

        // delete cropped image
        $file_url = getFileUrl($getMedia->location);
        $file_path = parse_url($file_url);
        Storage::disk('s3')->delete($file_path);

        // Delete full image
        $full_image = getFullImage($getMedia->location);
        $full_path = parse_url($full_image);
        Storage::disk('s3')->delete($full_path);
        Media::whereId($mediaId)->delete();

        return true;
    }

    public static function deleteSingleImage(int $id)
    {
        $media = Media::whereId($id);
        if (!$media->first()) {
            return false;
        }
        self::unlinkMedia($id);
        $media->delete();
        return true;
    }

    /**
     * @param string $data
     * @param string|null $fileName
     * @return Media|null
     *
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public static function addMediaFromBase64(string $data, string $fileName = null): ?Media
    {
        $fileData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data));
        $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString() . '.' . getFileExtensionFromBase64($data);
        file_put_contents($tmpFilePath, $fileData);
        $tmpFile = new File($tmpFilePath);
        $image = new UploadedFile(
            $tmpFile->getPathname(),
            $fileName ?: $tmpFile->getFilename(),
            $tmpFile->getMimeType(),
            0,
            true
        );

        return self::checkAndSaveImage($image) ?: null;
    }

    public static function generateBlurhashFor(Media $media)
    {
        try {
            if ($media->media_type_id == MediaTypes::PHOTOS) {
                $photoContentResized = Image::make(
                        Storage::get(self::getModifiedLocationFor($media))
                    )
                    ->resize(100, 100, function ($constraint) {
                        $constraint->aspectRatio();
                    })
                    ->encode('png')
                    ->__toString();
                $image = imagecreatefromstring($photoContentResized);
                $width = imagesx($image);
                $height = imagesy($image);
                $pixels = [];
                for ($y = 0; $y < $height; ++$y) {
                    $row = [];
                    for ($x = 0; $x < $width; ++$x) {
                        $index = imagecolorat($image, $x, $y);
                        $colors = imagecolorsforindex($image, $index);

                        $row[] = [$colors['red'], $colors['green'], $colors['blue']];
                    }
                    $pixels[] = $row;
                }
                $blurhash = Blurhash::encode($pixels);
                $media->blurhash = $blurhash;
                $media->width = $width;
                $media->height = $height;
                $media->save();
            }
        } catch (\Exception $e) {
            Log::error('Blurhash generation error: '.$e);
            Log::critical('media:blurhash error on Media ID: '.$media->id);
        }
    }

    public static function getModifiedLocationFor(Media $media)
    {
        $locationParts = explode('.', $media->location);
        $extension = array_pop($locationParts);
        if ($media->modification_suffix) {
            $modified = implode('.', $locationParts).$media->modification_suffix.'.'.$extension;
        }

        return $modified ?? $media->location;
    }
}
