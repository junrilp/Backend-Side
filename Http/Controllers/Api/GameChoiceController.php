<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GameChoiceRequest;
use App\Http\Resources\GameChoiceResource;
use App\Models\GameChoice;
use App\Repository\GameChoice\GameChoiceRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameChoiceController extends Controller
{
    use ApiResponser;

    private $gameChoiceRepository;

    public function __construct(GameChoiceRepository $gameChoiceRepository)
    {
        $this->gameChoiceRepository = $gameChoiceRepository;
    }

    /**
     * Create a game choice
     *
     * @param GameChoiceRequest $request
     * @return JsonResponse
     */
    public function store(GameChoiceRequest $request)
    {
        $data = $request->validated();

        try {
            $gameChoice = $this->gameChoiceRepository->store($data);

            return $this->successResponse(new GameChoiceResource($gameChoice), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Show single game choice
     *
     * @param GameChoice $gameChoice
     * @return JsonResponse
     */
    public function show(GameChoice $gameChoice)
    {
        return $this->successResponse(new GameChoiceResource($gameChoice), null, Response::HTTP_OK);
    }

    /**
     * Update game choice
     *
     * @param GameChoice $gameChoice
     * @param GameChoiceRequest $request
     * @return JsonResponse
     */
    public function update(GameChoice $gameChoice, GameChoiceRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameChoiceRepository->update($gameChoice, $data);

            return $this->successResponse(new GameChoiceResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a game choice
     *
     * @param GameChoice $gameChoice
     * @return JsonResponse
     */
    public function destroy(GameChoice $gameChoice)
    {
        try {
            $this->gameChoiceRepository->delete($gameChoice);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
