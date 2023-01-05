<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GameRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Repository\Game\GameRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameController extends Controller
{
    use ApiResponser;

    private $gameRepository;

    public function __construct(GameRepository $gameRepository)
    {
        $this->gameRepository = $gameRepository;
    }

    /**
     * Create a game
     *
     * @param GameRequest $request
     * @return JsonResponse
     */
    public function store(GameRequest $request)
    {
        $data = $request->validated();

        try {
            $game = $this->gameRepository->store($data);

            return $this->successResponse(new GameResource($game), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Show single game
     *
     * @param Game $game
     * @return JsonResponse
     */
    public function show(Game $game)
    {
        return $this->successResponse(new GameResource($game), null, Response::HTTP_OK);
    }

    /**
     * Update game choice
     *
     * @param Game $game
     * @param GameRequest $request
     * @return JsonResponse
     */
    public function update(Game $game, GameRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameRepository->update($game, $data);

            return $this->successResponse(new GameResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a game choice
     *
     * @param Game $game
     * @return JsonResponse
     */
    public function destroy(Game $game)
    {
        try {
            $this->gameRepository->delete($game);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get list of games
     *
     * @return JsonResponse
     */
    public function getAll()
    {
        try {
            $games = $this->gameRepository->getAll();

            return $this->successResponse(GameResource::collection($games), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
