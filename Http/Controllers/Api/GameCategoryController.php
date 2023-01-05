<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GameCategoryRequest;
use App\Http\Resources\GameCategoryResource;
use App\Models\GameCategory;
use App\Repository\GameCategory\GameCategoryRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameCategoryController extends Controller
{
    use ApiResponser;

    private $gameCategoryRepository;

    public function __construct(GameCategoryRepository $gameCategoryRepository)
    {
        $this->gameCategoryRepository = $gameCategoryRepository;
    }

    /**
     * Create new game category
     *
     * @param GameCategoryRequest $request
     * @return JsonResponse
     */
    public function store(GameCategoryRequest $request)
    {
        $data = $request->validated();

        try {
            $gameCategory = $this->gameCategoryRepository->store($data);

            return $this->successResponse(new GameCategoryResource($gameCategory), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Show single game category
     *
     * @param GameCategory $gameCategory
     * @return JsonResponse
     */
    public function show(GameCategory $gameCategory)
    {
        return $this->successResponse(new GameCategoryResource($gameCategory), null, Response::HTTP_OK);
    }

    /**
     * Update game category
     *
     * @param GameCategory $gameCategory
     * @return JsonResponse
     */
    public function update(GameCategory $gameCategory, GameCategoryRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameCategoryRepository->update($gameCategory, $data);

            return $this->successResponse(new GameCategoryResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a game category
     *
     * @param GameCategory $gameCategory
     * @return JsonResponse
     */
    public function destroy(GameCategory $gameCategory)
    {
        try {
            $this->gameCategoryRepository->delete($gameCategory);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
