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
use App\Http\Requests\DeleteSingleAlbumAttachment;
use App\Http\Requests\DeleteSingleAlbumAttachmentRequest;

class GroupAlbumController extends Controller
{

    use ApiResponser;

    /**
     * @param GetAlbumRequest $getAlbum
     * @param int $groupId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index(GetAlbumRequest $request, int $groupId)
    {
        try {
            $perPage = ($request->perPage) ? $request->perPage : 10;
            $album = AlbumRepository::getAlbums(DiscussionType::GROUPS, $groupId, $perPage);

            $result = AlbumResource::collection($album);
            return $this->successResponse($result, null, Response::HTTP_OK, true);

        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::index, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param GetAlbumRequest $getAlbum
     * @param int $groupId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function getAlbumById(int $groupId, int $albumId)
    {
        try {
            $album = AlbumRepository::getAlbumById(DiscussionType::GROUPS, $albumId);

            return $this->successResponse(new AlbumResource($album));
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::getAlbumById, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param StoreAlbumRequest $request
     * @param int $groupId
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(StoreAlbumRequest $request, int $groupId)
    {
        try {
            $album = AlbumRepository::storeAlbum(DiscussionType::GROUPS, $groupId, authUser()->id, $request->all());
            return $this->successResponse(new AlbumResource($album), null,   Response::HTTP_CREATED);
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::store, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param UpdateAlbumRequest $request
     * @param int $groupId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(UpdateAlbumRequest $request, int $groupId, int $id)
    {
        try {
            $album = AlbumRepository::updateAlbum(DiscussionType::GROUPS, $groupId, authUser()->id, $request->all(), $id);
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
     * @param int $groupId
     * @param int $id
     *
     * @return $result
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy(DestroyAlbumRequest $destroy, int $groupId, int $id)
    {
        try {
            $album = AlbumRepository::destroyAlbum(DiscussionType::GROUPS, $id);
            return $this->successResponse($album);
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::destroy, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete attachment from album items
     *
     * @param DeleteSingleAlbumAttachment $deleteSingleAlbumAttachment
     * @param int $groupId
     * @param int $eventAlbumId
     * @param int $mediaId
     *
     * @return [type]
     */
    public function deleteSingleAttachment(DeleteSingleAlbumAttachmentRequest $deleteSingleAlbumAttachment, int $groupId, int $groupAlbumId, int $id)
    {
        try {

            $album = AlbumRepository::destroyAlbumAttachment(DiscussionType::GROUPS, $groupAlbumId, $id);
            return $this->successResponse($album);
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::deleteSingleAttachment, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Upload Items into specific album
     *
     * @param StoreAlbumRequest $request
     * @param mixed $groupId
     * @param mixed $groupAlbumId
     *
     * @return [type]
     */
    public function uploadAlbumItem(StoreAlbumRequest $request, $groupId, $groupAlbumId)
    {
        try {
            $album = AlbumRepository::storeAlbumItems(DiscussionType::GROUPS, $groupAlbumId, $request->all(), authUser()->id);
            return $this->successResponse(AlbumResourceItems::collection($album), null, Response::HTTP_CREATED);
        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::uploadAlbumItem, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Retrive all items by specific album
     *
     * @param mixed $groupId
     * @param mixed $eventAlbumsId
     *
     * @return [type]
     */
    public function getAlbumItemById(Request $request, $groupId, $eventAlbumsId)
    {
        try {

            $perPage = ($request->perPage) ? $request->perPage : 12;
            $album = AlbumRepository::getAlbumsItemsById(DiscussionType::GROUPS, $eventAlbumsId, $perPage);

            $result = AlbumResourceItems::collection($album);
            return $this->successResponse($result, null, Response::HTTP_OK, true);

        } catch (Throwable $exception) {
            Log::critical('GroupAlbumController::getAlbumById, ' . $exception->getMessage());
            return $this->errorResponse('Something went wrong. ' . $exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
