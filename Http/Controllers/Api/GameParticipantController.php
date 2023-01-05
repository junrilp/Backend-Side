<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameParticipantStatus;
use App\Events\GameSessionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\GameParticipantRequest;
use App\Http\Requests\GameParticipantStatusRequest;
use App\Http\Resources\GameEntityResource;
use App\Http\Resources\GameParticipantResource;
use App\Models\GameParticipant;
use App\Repository\GameEntity\GameEntityRepository;
use App\Repository\GameParticipant\GameParticipantRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameParticipantController extends Controller
{
    use ApiResponser;

    private $gameParticipantRepository;
    private $gameEntityRepository;

    public function __construct(GameParticipantRepository $gameParticipantRepository, GameEntityRepository $gameEntityRepository)
    {
        $this->gameParticipantRepository = $gameParticipantRepository;
        $this->gameEntityRepository = $gameEntityRepository;
    }

    /**
     * Invite participant to game
     *
     * @param GameParticipantRequest $request
     * @return JsonResponse
     */
    public function invite(GameParticipantRequest $request)
    {
        $data = $request->validated();

        try {
            $this->gameParticipantRepository->invite($data);

            $participants = $this->gameParticipantRepository->getAll($data);

            return $this->successResponse(GameParticipantResource::collection($participants), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Display a participant
     *
     * @param GameParticipant $gameParticipant
     * @return JsonResponse
     */
    public function show(GameParticipant $gameParticipant)
    {
        return $this->successResponse(new GameParticipantResource($gameParticipant), null, Response::HTTP_OK);
    }

    /**
     * Update participant
     *
     * @param GameParticipant $gameParticipant
     * @param GameParticipantRequest $request
     * @return JsonResponse
     */
    public function update(GameParticipant $gameParticipant, GameParticipantRequest $request)
    {
        $data = $request->validated();

        try {
            $this->gameParticipantRepository->update($gameParticipant, $data);

            $participants = $this->gameParticipantRepository->getAll($data);

            return $this->successResponse(GameParticipantResource::collection($participants), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Join game
     *
     * @param GameParticipantRequest $request
     * @return JsonResponse
     */
    public function join(GameParticipantRequest $request)
    {
        $data = $request->validated();

        try {
            // Add user to participants
            $participant = $this->gameParticipantRepository->invite($data);

            // Then set status to joined
            $data['status'] = GameParticipantStatus::JOINED;

            $updated = $this->gameParticipantRepository->updateStatus($participant, $data);

            $data['game_entity_id'] = $updated->game_entity_id;

            $gameEntity = $this->gameEntityRepository->getById($data);

            $participants = $this->gameParticipantRepository->getAll($data);

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'participants' => GameParticipantResource::collection($participants)
            ];

            if ($data['status'] == GameParticipantStatus::JOINED) {
                broadcast(new GameSessionEvent($resources, GameParticipantStatus::SESSION_JOIN));
            }

            return $this->successResponse(GameParticipantResource::collection($participants), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update participant status
     *
     * @param GameParticipant $gameParticipant
     * @param GameParticipantStatusRequest $request
     * @return JsonResponse
     */
    public function updateStatus(GameParticipant $gameParticipant, GameParticipantStatusRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameParticipantRepository->updateStatus($gameParticipant, $data);

            $data['game_entity_id'] = $updated->game_entity_id;

            $gameEntity = $this->gameEntityRepository->getById($data);

            $participants = $this->gameParticipantRepository->getAll($data);

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'participants' => GameParticipantResource::collection($participants)
            ];

            if ($data['status'] == GameParticipantStatus::JOINED) {
                broadcast(new GameSessionEvent($resources, GameParticipantStatus::SESSION_JOIN));
            }

            return $this->successResponse($resources, null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete participant or leave game
     *
     * @param GameParticipant $gameParticipant
     * @return JsonResponse
     */
    public function destroy(GameParticipant $gameParticipant)
    {
        try {
            $data['status'] = GameParticipantStatus::LEAVE;

            $updated = $this->gameParticipantRepository->updateStatus($gameParticipant, $data);

            $data['game_entity_id'] = $updated->game_entity_id;

            $gameEntity = $this->gameEntityRepository->getById($data);

            $participants = $this->gameParticipantRepository->getAll($data);

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'participants' => GameParticipantResource::collection($participants)
            ];

            broadcast(new GameSessionEvent($resources, GameParticipantStatus::SESSION_LEAVE));

            $this->gameParticipantRepository->delete($gameParticipant);

            return $this->successResponse($resources, null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get list of participants
     *
     * @param GameParticipantRequest $request
     * @return JsonResponse|Response
     */
    public function getAllParticipants(GameParticipantRequest $request)
    {
        try {
            $participants = $this->gameParticipantRepository->getAll($request->all());

            $gameEntity = $this->gameEntityRepository->getById($request);

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'participants' => GameParticipantResource::collection($participants)
            ];

            return $this->successResponse($resources, null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
