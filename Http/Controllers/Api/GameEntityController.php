<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameParticipantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterGameEntityRequest;
use App\Http\Requests\GameEntityRequest;
use App\Http\Resources\GameEntityResource;
use App\Models\GameEntity;
use App\Repository\GameEntity\GameEntityRepository;
use App\Repository\GameParticipant\GameParticipantRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameEntityController extends Controller
{
    use ApiResponser;

    private $gameEntityRepository;
    private $gameParticipantRepository;

    public function __construct(GameEntityRepository $gameEntityRepository, GameParticipantRepository $gameParticipantRepository)
    {
        $this->gameEntityRepository = $gameEntityRepository;
        $this->gameParticipantRepository = $gameParticipantRepository;
    }

    /**
     * Create game entity
     *
     * @param GameEntityRequest $request
     * @return JsonResponse
     */
    public function store(GameEntityRequest $request)
    {
        $data = $request->validated();

        try {
            $gameEntity = $this->gameEntityRepository->store($data);

            // Add owner to participants
            $data['game_entity_id'] = $gameEntity->id;
            $data['user_id'] = $gameEntity->host_id;

            $participant = $this->gameParticipantRepository->invite($data);

            // Then set status to joined
            $data['status'] = GameParticipantStatus::JOINED;

            $this->gameParticipantRepository->updateStatus($participant, $data);

            return $this->successResponse(new GameEntityResource($gameEntity), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Display  game entity
     *
     * @param GameEntity $gameEntity
     * @return JsonResponse
     */
    public function show(GameEntity $gameEntity)
    {
        return $this->successResponse(new GameEntityResource($gameEntity), null, Response::HTTP_OK);
    }

    /**
     * Update game entity
     *
     * @param GameEntity $gameEntity
     * @param GameEntityRequest $request
     * @return JsonResponse
     */
    public function update(GameEntity $gameEntity, GameEntityRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameEntityRepository->update($gameEntity, $data);

            return $this->successResponse(new GameEntityResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a game entity
     *
     * @param GameEntity $gameEntity
     * @return JsonResponse
     */
    public function destroy(GameEntity $gameEntity)
    {
        try {
            $this->gameEntityRepository->delete($gameEntity);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Filter game entity
     *
     * @param FilterGameEntityRequest $request
     * @return JsonResponse|Response
     */
    public function filterGameEntities(FilterGameEntityRequest $request)
    {
        $data = $request->validated();

        try {
            $filtered = $this->gameEntityRepository->filter($data);

            return $this->successResponse(GameEntityResource::collection($filtered), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
