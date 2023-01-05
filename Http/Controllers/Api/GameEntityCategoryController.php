<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GameEntityCategoryRequest;
use App\Http\Resources\GameCategoryResource;
use App\Http\Resources\GameEntityCategoryResource;
use App\Models\GameEntityCategory;
use App\Repository\GameEntityCategory\GameEntityCategoryRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameEntityCategoryController extends Controller
{
    use ApiResponser;

    private $gameEntityCategoryRepository;

    public function __construct(GameEntityCategoryRepository $gameEntityCategoryRepository)
    {
        $this->gameEntityCategoryRepository = $gameEntityCategoryRepository;
    }

    /**
     * Create game entity category
     *
     * @param GameEntityCategoryRequest $request
     * @return JsonResponse
     */
    public function store(GameEntityCategoryRequest $request)
    {
        $data = $request->validated();

        try {
            $gameEntityCategory = $this->gameEntityCategoryRepository->store($data);

            return $this->successResponse(new GameEntityCategoryResource($gameEntityCategory), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Show single game entity category
     *
     * @param GameEntityCategory $gameEntityCategory
     * @return JsonResponse
     */
    public function show(GameEntityCategory $gameEntityCategory)
    {
        return $this->successResponse(new GameEntityCategoryResource($gameEntityCategory), null, Response::HTTP_OK);
    }

    /**
     * Update game entity category
     *
     * @param GameEntityCategory $gameEntityCategory
     * @param GameEntityCategoryRequest $request
     * @return JsonResponse
     */
    public function update(GameEntityCategory $gameEntityCategory, GameEntityCategoryRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameEntityCategoryRepository->update($gameEntityCategory, $data);

            return $this->successResponse(new GameEntityCategoryResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete game entity category
     *
     * @param GameEntityCategory $gameEntityCategory
     * @return JsonResponse
     */
    public function destroy(GameEntityCategory $gameEntityCategory)
    {
        try {
            $this->gameEntityCategoryRepository->delete($gameEntityCategory);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get all game entity categories
     *
     * @return JsonResponse
     */
    public function getAll()
    {
        try {
            $categories = $this->gameEntityCategoryRepository->getAll();

            return $this->successResponse(GameCategoryResource::collection($categories), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
