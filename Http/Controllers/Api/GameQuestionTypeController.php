<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GameQuestionTypeRequest;
use App\Http\Resources\GameQuestionTypeResource;
use App\Models\GameQuestionType;
use App\Repository\GameQuestionType\GameQuestionTypeRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameQuestionTypeController extends Controller
{
    use ApiResponser;

    private $gameQuestionTypeRepository;

    public function __construct(GameQuestionTypeRepository $gameQuestionTypeRepository)
    {
        $this->gameQuestionTypeRepository = $gameQuestionTypeRepository;
    }

    /**
     * Create a question type
     *
     * @param GameQuestionTypeRequest $request
     * @return JsonResponse
     */
    public function store(GameQuestionTypeRequest $request)
    {
        $data = $request->validated();

        try {
            $gameQuestionType = $this->gameQuestionTypeRepository->store($data);

            return $this->successResponse(new GameQuestionTypeResource($gameQuestionType), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Display question type
     *
     * @param GameQuestionType $gameQuestionType
     * @return JsonResponse|Response
     */
    public function show(GameQuestionType $gameQuestionType)
    {
        return $this->successResponse(new GameQuestionTypeResource($gameQuestionType), null, Response::HTTP_OK);
    }

    /**
     * Update question type
     *
     * @param GameQuestionType $gameQuestionType
     * @return JsonResponse|Response
     */
    public function update(GameQuestionType $gameQuestionType, GameQuestionTypeRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameQuestionTypeRepository->update($gameQuestionType, $data);

            return $this->successResponse(new GameQuestionTypeResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update question type
     *
     * @param GameQuestionType $gameQuestionType
     * @return JsonResponse|Response
     */
    public function destroy(GameQuestionType $gameQuestionType)
    {
        try {
            $this->gameQuestionTypeRepository->delete($gameQuestionType);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
