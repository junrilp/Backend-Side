<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameParticipantStatus;
use App\Events\GameQuestionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\GameQuestionRequest;
use App\Http\Resources\GameEntityResource;
use App\Http\Resources\GameQuestionResource;
use App\Models\GameQuestion;
use App\Repository\GameEntity\GameEntityRepository;
use App\Repository\GameQuestion\GameQuestionRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class GameQuestionController extends Controller
{
    use ApiResponser;

    private $gameQuestionRepository;
    private $gameEntityRepository;

    public function __construct(GameQuestionRepository $gameQuestionRepository, GameEntityRepository $gameEntityRepository)
    {
        $this->gameQuestionRepository = $gameQuestionRepository;
        $this->gameEntityRepository = $gameEntityRepository;
    }

    /**
     * Create question
     *
     * @param GameQuestionRequest $request
     * @return JsonResponse
     */
    public function store(GameQuestionRequest $request)
    {
        $data = $request->validated();

        try {
            $gameQuestion = $this->gameQuestionRepository->store($data);

            $questionsByEntityId = $this->gameQuestionRepository->getQuestionsByEntityId($data['game_entity_id']);

            $gameEntity = $this->gameEntityRepository->getById($data);

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'questions' => GameQuestionResource::collection($questionsByEntityId)
            ];

            broadcast(new GameQuestionEvent($resources, GameParticipantStatus::ANSWER_SUBMITTED));

            return $this->successResponse(new GameQuestionResource($gameQuestion), null, Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Gets question by game entity id
     *
     * @param $entityId
     * @return JsonResponse
     */
    public function getQuestionsByEntityId($entityId)
    {
        $questionsByEntityId = $this->gameQuestionRepository->getQuestionsByEntityId($entityId);

        return $this->successResponse(GameQuestionResource::collection($questionsByEntityId), null, Response::HTTP_OK);
    }

    /**
     * Display question
     *
     * @param $gameQuestion
     * @return JsonResponse
     */
    public function show($gameQuestion)
    {
        // Can't use route model binding, not accepted in resource. Expects repository instance
        $question = $this->gameQuestionRepository->show($gameQuestion);

        return $this->successResponse(new GameQuestionResource($question), null, Response::HTTP_OK);
    }

    /**
     * Update question
     *
     * @param GameQuestion $gameQuestion
     * @param GameQuestionRequest $request
     * @return JsonResponse
     */
    public function update(GameQuestion $gameQuestion, GameQuestionRequest $request)
    {
        $data = $request->validated();

        try {
            $updated = $this->gameQuestionRepository->update($gameQuestion, $data);

            $questionsByEntityId = $this->gameQuestionRepository->getQuestionsByEntityId($data['game_entity_id']);

            $gameEntity = $this->gameEntityRepository->getById($data);

            $resources = [
                'game_entity' => new GameEntityResource($gameEntity),
                'questions' => GameQuestionResource::collection($questionsByEntityId)
            ];

            $action = GameParticipantStatus::SUBMITTED_ANSWER_PENDING;

            if ($data['status'] == GameParticipantStatus::SUBMITTED_ANSWER_APPROVED) {
                $action = GameParticipantStatus::ANSWER_APPROVED;
            } elseif ($data['status'] == GameParticipantStatus::SUBMITTED_ANSWER_REJECTED) {
                $action = GameParticipantStatus::ANSWER_REJECTED;
            }

            broadcast(new GameQuestionEvent($resources, $action));

            return $this->successResponse(new GameQuestionResource($updated), null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a question
     *
     * @param GameQuestion $gameQuestion
     * @return JsonResponse|Response
     */
    public function destroy(GameQuestion $gameQuestion)
    {
        try {
            $this->gameQuestionRepository->delete($gameQuestion);

            return $this->successResponse([], null, Response::HTTP_OK);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
