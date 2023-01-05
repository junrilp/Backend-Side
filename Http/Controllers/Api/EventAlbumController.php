<?php

namespace App\Http\Controllers\Api;

use Throwable;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Enums\DiscussionType;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlbumResource;
use App\Http\Requests\GetAlbumRequest;
use App\Http\Requests\StoreAlbumRequest;
use App\Http\Requests\UpdateAlbumRequest;
use App\Repository\Album\AlbumRepository;
use App\Http\Requests\DestroyAlbumRequest;
use App\Http\Resources\AlbumResourceItems;
use App\Http\Requests\DeleteSingleAlbumAttachmentRequest;

class EventAlbumController extends Controller
{

    use ApiResponser;

    /**
     * @param GetAlbumRequest $getAlbum
     * @param int $eventId
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(GetAlbumRequest $request, int $eventId)
    {
        try {
            $perPage = ($request->perPage) ? $request->perPage : 10;
            $album = AlbumRepository::getAlbums(DiscussionType::EVENTS, $eventId, $perPage);

            $result = AlbumResource::collection($album);
            return $this->successResponse($result, null, Response::HTTP_OK, true);
            
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::index, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param GetAlbumRequest $getAlbum
     * @param int $eventId
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getAlbumById(int $eventId, int $albumId)
    {
        try {
            $album = AlbumRepository::getAlbumById(DiscussionType::EVENTS, $albumId);
            
            return $this->successResponse(new AlbumResource($album));
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::getAlbumById, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param StoreAlbumRequest $request
     * @param int $eventId
     *  
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(StoreAlbumRequest $request, int $eventId)
    {
        try {
            $album = AlbumRepository::storeAlbum(DiscussionType::EVENTS, $eventId, authUser()->id, $request->all());
            return $this->successResponse(new AlbumResource($album), null,   Response::HTTP_CREATED);
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::store, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param UpdateAlbumRequest $request
     * @param int $eventId
     * @param int $id
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateAlbumRequest $request, int $eventId, int $id)
    {
        try {
            $album = AlbumRepository::updateAlbum(DiscussionType::EVENTS, $eventId, authUser()->id, $request->all(), $id);
            return $this->successResponse(new AlbumResource($album));
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::update, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove Album along with album items
     * by just passing the album id
     * 
     * @param DestroyAlbumRequest $destroy
     * @param int $eventId
     * @param int $id
     * 
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DestroyAlbumRequest $destroy, int $eventId, int $id)
    {
        try {
            $album = AlbumRepository::destroyAlbum(DiscussionType::EVENTS, $id);
            return $this->successResponse($album);
        } catch (Throwable $exception) {
            Log::critical('EventAlbumController::destroy, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete attachment from album items
     * 
     * @param DeleteSingleAlbumAttachmentRequest $deleteSingleAlbumAttachment
     * @param int $eventId
     * @param int $eventAlbumId
     * @param int $mediaId
     * 
     * @return [type]
     */
    public function deleteSingleAttachment(DeleteSingleAlbumAttachmentRequest $deleteSingleAlbumAttachment, int $eventId, int $eventAlbumId, int $id)
    {
        try {
            
            $album = AlbumRepository::destroyAlbumAttachment(DiscussionType::EVENTS, $eventAlbumId, $id);
            return $this->successResponse($album);
        } catch (Throwable $exception) {
            Log::critical('EventAlbumController::deleteSingleAttachment, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Upload Items into specific album
     * 
     * @param StoreAlbumRequest $request
     * @param mixed $eventId
     * @param mixed $eventAlbumsId
     * 
     * @return [type]
     */
    public function uploadAlbumItem(StoreAlbumRequest $request, $eventId, $eventAlbumsId)
    {
        try {
            $album = AlbumRepository::storeAlbumItems(DiscussionType::EVENTS, $eventAlbumsId, $request->all(), authUser()->id);
            return $this->successResponse(AlbumResourceItems::collection($album), null, Response::HTTP_CREATED);
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::uploadAlbumItem, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Retrive all items by specific album
     * 
     * @param mixed $eventId
     * @param mixed $eventAlbumsId
     * 
     * @return [type]
     */
    public function getAlbumItemById(Request $request, $eventId, $eventAlbumsId)
    {
        try {

            $perPage = ($request->perPage) ? $request->perPage : 10;
            $album = AlbumRepository::getAlbumsItemsById(DiscussionType::EVENTS, $eventAlbumsId, $perPage);

            $result = AlbumResourceItems::collection($album);
            return $this->successResponse($result, null, Response::HTTP_OK, true);

        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::getAlbumById, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
