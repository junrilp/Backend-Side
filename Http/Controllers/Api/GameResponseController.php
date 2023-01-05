<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameParticipantStatus;
use App\Events\GameSessionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\GameResponseRequest;
use App\Http\Resources\GameEntityResource;
use App\Http\Resources\GameParticipantResource;
use App\Repository\GameEntity\GameEntityRepository;
use App\Repository\GameParticipant\GameParticipantRepository;
use App\Repository\GameResponse\GameResponseRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameResponseController extends Controller
{
    use ApiResponser;

    private $gameResponseRepository;
    private $gameParticipantRepository;
    private $gameEntityRepository;

    public function __construct(GameResponseRepository $gameResponseRepository, GameParticipantRepository $gameParticipantRepository, GameEntityRepository $gameEntityRepository)
    {
        $this->gameResponseRepository = $gameResponseRepository;
        $this->gameParticipantRepository = $gameParticipantRepository;
        $this->gameEntityRepository = $gameEntityRepository;
    }

    /**
     * Update game choice
     * @param GameResponseRequest $request
     * @return JsonResponse|Response
     */
    public function submit(GameResponseRequest $request)
    {
        $data = $request->validated();

        try {
            $this->gameResponseRepository->store($data);

            $gameEntity = $this->gameEntityRepository->getById($data);

            $participants = $this->gameParticipantRepository->getAll($request->all());

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'participants' => GameParticipantResource::collection($participants)
            ];

            broadcast(new GameSessionEvent($resources, GameParticipantStatus::ANSWER_SUGGESTED));

            return $this->successResponse(GameParticipantResource::collection($participants), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
