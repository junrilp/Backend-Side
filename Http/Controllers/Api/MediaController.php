<?php

namespace App\Http\Controllers\Api;

use App\Enums\MediaTypes;
use App\Enums\WallType;
use App\Events\WallPosted;
use App\Events\WallPostedEvent;
use App\Events\WallPostedGroup;
use App\Forms\MediaForm;
use App\Forms\RegistrationForm;
use App\Http\Controllers\Controller;
use App\Http\Resources\Media as MediaResource2;
use App\Http\Resources\MediaResource;
use App\Http\Resources\UserMediasResource;
use App\Models\EventWallAttachment;
use App\Models\GroupWallAttachment;
use App\Models\Media;
use App\Models\UserDiscussionAttachment;
use App\Models\UserPhoto;
use App\Traits\ApiResponser;

use Aws\Sns\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repository\Media\MediaRepository;

class MediaController extends Controller
{
    use ApiResponser;

    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function uploadPhoto(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = RegistrationForm::uploadPhoto($request);
            DB::commit();
            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image Id not found'
                ],404);
            }

            return response()->json([
                'success' => true,
                'data' => new UserMediasResource(
                    UserPhoto::where('user_id', Auth::user()->id)->get()),
            ],201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

     /**
     * @param Request $request
     *
     * @return [type]
     */
    public function uploadPhotoById(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = RegistrationForm::uploadPhotoById($request);
            DB::commit();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image Id not found'
                ],404);
            }

            return response()->json([
                'success' => true,
                'data' => new UserMediasResource(
                    UserPhoto::where('user_id', Auth::user()->id)->get()),
            ],201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * @return [type]
     */
    public function removeAdditionalPhotoUserId()
    {
        try
        {
            $userId = '';
            $photos = UserPhoto::where('user_id', $userId)
                        ->get();
            foreach ($photos as $photo) {
                $imageId = $photo->media_id;
                $photoId = $photo->id;
                $data = MediaForm::unlinkMedia($imageId);
                UserPhoto::whereId($photoId)
                                ->delete();
            }
            return response()->json([
                'success' => true,
                'message' => $data
            ]);
        }
        catch(\Exception $e) {
            throw $e;
        }

    }

    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function removePhoto(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = MediaForm::removePhoto($request);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function removeMultipleImageByIds(Request $request)
    {
        try
        {
            $ids = $request->ids;
            $photos = UserPhoto::whereIn('id',  $ids)
                        ->get();
            $media = [];
            $getPhoto = [];
            foreach ($photos as $photo) {
                $imageId = $photo->media_id;
                $photoId = $photo->id;
                $media[] = MediaForm::unlinkMedia($imageId);
                $photoData = UserPhoto::whereId($photoId);
                $getPhoto[] = $photoData->first();
                $photoData->delete();
            }
            return response()->json([
                'success' => true,
                'data' => new UserMediasResource(
                    UserPhoto::where('user_id', Auth::user()->id)->get()),
            ]);
        }
        catch(\Exception $e) {
            throw $e;
        }
    }

    public function removeMediaPhotoUserId()
    {
        try
        {
            $userId = '';
            $photos = Media::where('user_id', $userId);
            foreach ($photos->get() as $photo) {
                $imageId = $photo->image;
                $data = MediaForm::unlinkMedia($imageId);
                $photos->delete();
            }
            return response()->json([
                'success' => true,
                'message' => new UserMediasResource(
                    UserPhoto::where('user_id', Auth::user()->id)->get()),
            ]);
        }
        catch(\Exception $e) {
            throw $e;
        }
    }


    public function uploadSingleImage(Request $request)
    {
        try {

            if (!$request->image) {
                return [
                    'success' => false,
                    'message' => 'No image attached'
                ];
            }

            DB::beginTransaction();
            $data = MediaForm::addMedia($request->file('image'), $request->file('image_full'), null ?? 0, null ?? 0);
            DB::commit();
            return response()->json([
                'success' => true,
                'data' => new MediaResource(Media::findOrFail($data->id))
            ],201);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }


    public function deleteSingleImage(Request $request)
    {
        try {
            return MediaForm::deleteSingleImage($request);
        }
        catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, MediaForm $mediaForm)
    {
        if (is_array($request->file)) {
            return $this->uploadMultipleFile($request->file, $mediaForm);
        }

        $media = $mediaForm->uploadPhoto($request->file);
        MediaRepository::generateBlurhashFor($media);

        return response()->json([
            'data' => new MediaResource2($media),
        ], 201);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeCrop(Request $request, MediaForm $mediaForm, int $id)
    {
        $media = Media::find($id);
        $updatedMedia = $mediaForm->uploadCropPhotoFor($media, $request->file);
        MediaRepository::generateBlurhashFor($updatedMedia);

        return response()->json([
            'data' => new MediaResource2($updatedMedia),
        ], 201);
    }

    /**
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {

        if ($id==0) {
            return false;
        }
        $media = Media::find($id);

        return response()->json(['data' => new MediaResource2($media)]);
    }

    public function uploadMultipleFile($files, $mediaForm)
    {
        $data = array();
        foreach ($files as $file) {
            $data[] = $mediaForm->uploadPhoto($file);
        }
        return response()->json([
            'data' => MediaResource2::collection($data)
        ], 201);
    }


    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function uploadVideo(Request $request)
    {

        try {

            $media = MediaForm::addMedia($request->file, null, MediaTypes::VIDEOS, authUser()->id, null );

            return $this->successResponse(new MediaResource2($media));

        } catch (\Exception $e) {

            return $this->errorResponse('Sorry we are unable to upload your video. Please try again.' . $e->getMessage(), Response::HTTP_BAD_REQUEST);

        }
    }

    public function mediaCompleted(Request $request){

        // Instantiate the Message and Validator
        $output = json_decode(file_get_contents("php://input"), true);

        if (isset($output['SubscribeURL'])) {
            // Do a basic curl to confirm the subscription if available. Otherwise, dump output to the database
            if (function_exists('curl_version')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $output['SubscribeURL']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $curlOutput = curl_exec($ch);
                curl_close($ch);
                // Exit after done. We can't handle the test payload any further
                exit();
            } else {
                // Can't continue anyway if the subscription isn't confirmed
                exit();
            }
        }

        $output = json_decode(file_get_contents("php://input"), true);
        $message = Message::fromRawPostData();
        $messageArray = json_decode($message['Message'], true);
        $mediaId = $messageArray['userMetadata']['media_id'];

        if ($mediaId) {
            Log::info('webhook');
            Log::info($mediaId . 'webhook');

            $media = Media::find($mediaId);

            /* Users */
            $userDiscussionAttachment = UserDiscussionAttachment::where('media_id', $mediaId)->first();

            if ($userDiscussionAttachment) {
                $discussion = $userDiscussionAttachment->userDiscussion;
                $discussion->load('attachments.media');

                broadcast(new WallPosted($discussion));
            }

            /* Events */
            $eventWallAttachment = EventWallAttachment::where('media_id', $mediaId)->first();

            if ($eventWallAttachment) {
                $eventWallPost = $eventWallAttachment->eventWallDiscussion;
                $eventWallPost->load('attachments.media');

                broadcast(new WallPostedEvent($eventWallPost, WallType::EVENT, $eventWallPost->user_id));
            }

            /* Groups */
            $groupWallAttachment = GroupWallAttachment::where('media_id', $mediaId)->first();

            if ($groupWallAttachment) {
                $groupWallPost = $groupWallAttachment->groupWallDiscussion;
                $groupWallPost->load('attachments.media');

                broadcast(new WallPostedGroup($groupWallPost, WallType::GROUP, $groupWallPost->user_id));
            }

            $media->processing_status = 2;
            $media->save();

        }

    }


}
