<?php

namespace App\Http\Controllers\Api\Game;

use App\Enums\WYRActions;
use App\Events\WYRQuestionEvent;
use App\Events\WYRSessionEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\GetParticipantsRequest;
use App\Http\Requests\Game\GetQuestionsRequest;
use App\Http\Requests\Game\GetSessionsRequest;
use App\Http\Requests\Game\InvitationRequest;
use App\Http\Requests\Game\SubmitAnswerRequest;
use App\Http\Requests\Game\SubmitQuestionRequest;
use App\Http\Requests\Game\WYRRequest;
use App\Http\Resources\Game\AnswerResource;
use App\Http\Resources\Game\InvitationResource;
use App\Http\Resources\Game\JoinedResource;
use App\Http\Resources\Game\SessionResource;
use App\Http\Resources\Game\WYRResource;
use App\Http\Resources\UserResource2;
use App\Models\Games\Invitation;
use App\Models\Games\Session;
use App\Models\Games\WYR\Answer;
use App\Models\Games\WYR\WYR;
use App\Models\User;
use App\Repository\WYR\WYRRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use Throwable;

class WYRController extends Controller
{
    use ApiResponser;

    private $wyrRepository;

    public function __construct(WYRRepository $wyrRepository)
    {
        $this->wyrRepository = $wyrRepository;
    }

    public function getAnswerMatches(User $user)
    {
        try {
            $matchingAnswers = $this->wyrRepository->matchingAnswersByUser($user);

            if ($matchingAnswers) {
                return $this->successResponse($matchingAnswers, null, Response::HTTP_OK);
            }

            return $this->errorResponse('No matches found.', Response::HTTP_BAD_REQUEST);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(WYRRequest $request)
    {
        $data = $request->validated();

        try {
            $host = $this->wyrRepository->getHost($data['host']);

            if ($host) {

                $wyr = $this->wyrRepository->storeWYR($data);

                $session = $this->wyrRepository->createWYRSession($host->id, $wyr->id);

                $participants = [
                    'session_id' => $session->id,
                    'host_id' => $host->id,
                    'user_id' => authUser()->id
                ];

                $this->wyrRepository->createSessionParticipants($participants);

                $data['session'] = $session->id;

                $resource = $this->buildResource($wyr, $host);

                return $this->successResponse(new WYRResource($resource), null, Response::HTTP_CREATED);
            } else {
                return $this->errorResponse('Invalid host', Response::HTTP_BAD_REQUEST);
            }
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    private function buildResource($wyr, $host)
    {
        return [
            'wyr' => $wyr,
            'user' => $host
        ];
    }

    public function delete(WYR $wyr)
    {
        $host = $this->wyrRepository->getHost($wyr->host);

        try {
            $resource = $this->buildResource($wyr, $host);

            $this->wyrRepository->deleteWYR($wyr);

            return $this->successResponse(new WYRResource($resource), null, Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    // TODO: What happens when host leaves the game? Transfer to remaining users?
    public function leave(WYR $wyr, User $user)
    {
        try {
            $this->wyrRepository->leaveWYRSession($wyr->id, $user->id);

            $data = [
                'all_participants' => 0,
                'include_user' => 0
            ];

            $participants = [
                'current_players' => $this->wyrRepository->getParticipants($wyr, $data)
            ];

            broadcast(new WYRSessionEvent($wyr, WYRActions::SESSION_LEAVE));

            return $this->successResponse($participants, $user->user_name . ' left the game.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(WYR $wyr)
    {
        $host = $this->wyrRepository->getHost($wyr->host);

        $resource = $this->buildResource($wyr, $host);

        return $this->successResponse(new WYRResource($resource), null, Response::HTTP_OK);
    }

    public function getQuestionsBySession(Session $session, GetQuestionsRequest $request)
    {
        $data = $request->validated();

        $questions = $this->wyrRepository->getQuestionsBySessions($session, $data['submitted-by'] ?? 'all');

        try {
            return $this->successResponse(AnswerResource::collection($questions), 'Questions by session id.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getParticipants(WYR $wyr, GetParticipantsRequest $request)
    {
        $data = $request->validated();

        try {
            $participants = $this->wyrRepository->getParticipants($wyr, $data);

            return $this->successResponse(UserResource2::collection($participants), 'WYR participants.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getSessions(GetSessionsRequest $request)
    {

        $data = $request->validated();

        $sessions = $this->wyrRepository->getAllSessions($data);

        return $this->successResponse(SessionResource::collection($sessions), null, Response::HTTP_OK);
    }

    public function invite(InvitationRequest $request)
    {
        $data = $request->validated();

        $invitation = $this->wyrRepository->inviteUser($data);

        if ($invitation) {
            return $this->successResponse(new InvitationResource($invitation), 'Invite sent.', Response::HTTP_CREATED);
        }

        return $this->errorResponse('Invite not sent, please try again.', Response::HTTP_BAD_REQUEST);
    }

    public function join(WYR $wyr)
    {
        $user = authUser();

        try {
            $this->wyrRepository->joinSession($wyr, $user);

            $resource = [
                'wyr' => $wyr,
                'joined' => $user,
            ];

            broadcast(new WYRSessionEvent($wyr, WYRActions::SESSION_JOIN));

            return $this->successResponse(new JoinedResource($resource), $user->user_name . ' joined the session.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function submitQuestions(WYR $wyr, SubmitQuestionRequest $request)
    {
        $data = $request->validated();

        $data['host'] = $wyr->host;

        $data['wyrId'] = $wyr->id;

        $data['wyrSessionId'] = $wyr->id;;

        $data['user'] = Authuser();

        try {
            $answers = $this->wyrRepository->submitQuestions($data);

            broadcast(new WYRQuestionEvent($answers[0], WYRActions::ANSWER_SUGGESTED));
            broadcast(new WYRQuestionEvent($answers[1], WYRActions::ANSWER_SUGGESTED));

            return $this->successResponse(new AnswerResource($answers[0]), 'Questions submitted.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function accept(Invitation $invitation)
    {
        try {
            $this->wyrRepository->acceptInvitation($invitation);

            $invitationData = $invitation;

            $invitation->delete();

            return $this->successResponse($invitationData, $invitation->recipient->user_name . ' accepted the invitation.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse('Invite not sent, please try again.', Response::HTTP_BAD_REQUEST);
        }
    }

    public function submitAnswer(SubmitAnswerRequest $request)
    {
        $data = $request->validated();

        try {
            $answer = Answer::find($data['answer_id']);

            $userAnswer = $this->wyrRepository->submitAnswer($data, $answer);

            broadcast(new WYRQuestionEvent(json_decode($userAnswer), WYRActions::ANSWER_SUBMITTED));

            return $this->successResponse(new AnswerResource(json_decode($userAnswer)), 'Answer submitted.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(WYR $wyr, WYRRequest $request)
    {
        $data = $request->validated();

        $host = $this->wyrRepository->getHost($data['host']);

        if ($host) {

            try {
                $updatedWYR = $this->wyrRepository->updateWYR($wyr, $data);

                $resource = $this->buildResource($updatedWYR, $host);

                return $this->successResponse(new WYRResource($resource), null, Response::HTTP_OK);
            } catch (Throwable $e) {
                return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        } else {
            return $this->errorResponse('Invalid host', Response::HTTP_BAD_REQUEST);
        }
    }

    public function approveQuestion($answerSet)
    {
        try {
            $updatedAnswers = $this->wyrRepository->approveQuestion($answerSet);

            return $this->successResponse(AnswerResource::collection($updatedAnswers), 'Questions approved.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function declineQuestion($answerSet)
    {
        try {
            $updatedAnswer = $this->wyrRepository->declineQuestion($answerSet);

            return $this->successResponse(AnswerResource::collection($updatedAnswer), 'Question declined.', Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
