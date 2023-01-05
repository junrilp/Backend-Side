<?php

namespace App\Repository\WYR;

use App\Enums\GameType;
use App\Enums\WYRParticipantOptions;
use App\Enums\WYRQuestionStatus;
use App\Enums\WYRSessionFlag;
use App\Http\Resources\UserResource2;
use App\Models\Games\Invitation;
use App\Models\Games\Participant;
use App\Models\Games\Session;
use App\Models\Games\WYR\Answer;
use App\Models\Games\WYR\UserAnswer;
use App\Models\Games\WYR\WYR;
use App\Models\User;

class WYRRepository implements WYRInterface
{

    public static function getHost($userId)
    {
        return User::find($userId);
    }

    public static function createWYRSession($hostId, $wyrId)
    {
        return Session::create([
            'game_id' => GameType::WYR, // Fixed id for now as this is the only game
            'game_entity_id' => $wyrId,
            'host_id' => $hostId,
            'user_id' => authUser()->id,
            'total_participants' => 1,
            'is_popular' => WYRSessionFlag::NOT_POPULAR,
        ]);
    }

    public static function createSessionParticipants($participant)
    {

        return Participant::create([
            'session_id' => $participant['session_id'],
            'host_id' => $participant['host_id'],
            'include_current_user' => WYRSessionFlag::INCLUDE_CURRENT_USER,
        ]);
    }

    public static function storeWYR($data)
    {
        return WYR::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'duration' => $data['duration'],
            'host' => $data['host'],
        ]);
    }

    public static function deleteWYR(WYR $wyr)
    {
        $wyr->delete();
    }

    public static function leaveWYRSession($wyrId, $userId)
    {
        return Session::where('game_entity_id', $wyrId)
            ->where('user_id', $userId)
            ->delete();
    }

    public static function getParticipants(WYR $wyr, $data)
    {
        if ($data['all_participants'] == WYRParticipantOptions::ALL) {
            $session = Session::where('game_entity_id', $wyr->id)
                ->withTrashed()
                ->get();
        } else {
            $session = Session::where('game_entity_id', $wyr->id)
                ->whereNull('deleted_at')
                ->get();
        }

        if ($data['include_user'] == WYRParticipantOptions::EXCLUDE_USER) {
            $session = $session->reject(function ($value, $key) {
                return $value->user_id == AuthUser()->id;
            });
        }

        return User::whereIn('id', $session->pluck('user_id'))
            ->get();
    }

    public static function getAllSessions($data)
    {
        $query = WYR::whereNull('deleted_at');

        if ($data['search-type'] == 'popular') {
            $query = $query->where('is_popular', WYRSessionFlag::POPULAR);
        } elseif ($data['search-type'] == 'my-session') {
            $query = $query->where('host', AuthUser()->id);
        } elseif ($data['search-type'] == 'all') {
            $query = $query->withTrashed();
        }

        if (isset($data['perPage'])) {
            $sessions = $query->paginate($data['perPage']);
        } else {
            $sessions = $query->get();
        }

        return $sessions;
    }

    public static function inviteUser($data)
    {
        return Invitation::firstOrCreate([
            'session_id' => $data['wyr_id'],
            'game_id' => GameType::WYR,
            'sender_id' => AuthUser()->id,
            'recipient_id' => $data['recipient_id'],
        ]);
    }

    public static function submitQuestions($data)
    {
        $approved = WYRQuestionStatus::PENDING;

        // Approve question if host creates it
        if ($data['host'] == Authuser()->id) {
            $approved = WYRQuestionStatus::APPROVED;
        }

        $answers[] = Answer::firstOrCreate(
            [
                'wyr_id' => $data['wyrId'],
                'session_id' => $data['wyrSessionId'],
                'user_id' => $data['user']->id,
                'choice' => $data['answer1'],
                'is_approved' => $approved,
            ]
        );

        $answers[] = Answer::firstOrCreate(
            [
                'wyr_id' => $data['wyrId'],
                'session_id' => $data['wyrSessionId'],
                'user_id' => $data['user']->id,
                'choice' => $data['answer2'],
                'is_approved' => $approved
            ]
        );

        $setId = $answers[0]->id + $answers[1]->id;

        $answers[0]->answer_set_id = $setId;
        $answers[0]->save();
        $answers[0]['submitted_by'] = new UserResource2($data['user']);

        $answers[1]->answer_set_id = $setId;
        $answers[1]->save();
        $answers[1]['submitted_by'] = new UserResource2($data['user']);

        return $answers;
    }

    public static function joinSession(WYR $wyr, User $user)
    {
        return Session::firstOrCreate([
            'game_id' => GameType::WYR, // Fixed id for now as this is the only game
            'game_entity_id' => $wyr->id,
            'host_id' => $wyr->host,
            'user_id' => authUser()->id,
        ]);
    }

    public static function acceptInvitation(Invitation $invitation)
    {
        return Session::firstOrCreate([
            'game_id' => GameType::WYR, // Fixed id for now as this is the only game
            'game_entity_id' => $invitation->session_id,
            'host_id' => $invitation->wyr->host,
            'user_id' => $invitation->recipient_id,
        ]);
    }

    public static function submitAnswer($data, Answer $answer)
    {
        $userAnswer = UserAnswer::firstOrCreate([
            'session_id' => $data['wyr_id'],
            'user_id' => $data['user_id'],
            'answer_id' => $data['answer_id'],
            'answer_set_id' => $answer->answer_set_id,
        ]);

        return json_encode(
            collect([
                'id' => $userAnswer->id,
                'wyr_id' => $data['wyr_id'],
                'user_id' => $data['user_id'],
                'choice' => $answer->choice,
                'answer_set_id' => $answer->answer_set_id,
                'submitted_by' => new UserResource2(User::find($answer->user_id))
            ])
        );
    }

    public static function updateWYR(WYR $wyr, $data)
    {
        $wyr->title = $data['title'];
        $wyr->description = $data['description'];
        $wyr->duration = $data['duration'];
        $wyr->host = $data['host'];
        $wyr->is_ended = $data['is_ended'];
        $wyr->save();

        return $wyr;
    }

    public static function declineQuestion($answerSet)
    {
        $answers = Answer::where('answer_set_id', $answerSet)
            ->get();

        $submittedBy = User::find($answers[0]->user_id);

        foreach ($answers as $answer) {
            $answer->is_approved = WYRQuestionStatus::DECLINED;
            $answer->save();
            $answer['submitted_by'] = new UserResource2($submittedBy);
        }

        return $answers;
    }

    public static function approveQuestion($answerSet)
    {
        $answers = Answer::where('answer_set_id', $answerSet)
            ->get();

        $submittedBy = User::find($answers[0]->user_id);

        foreach ($answers as $answer) {
            $answer->is_approved = WYRQuestionStatus::APPROVED;
            $answer->save();
            $answer['submitted_by'] = new UserResource2($submittedBy);
        }

        return $answers;
    }

    public static function getQuestionsBySessions(Session $session, $submittedBy)
    {
        $query = Answer::where('session_id', $session->id);

        $wyr = $session->wyr;

        if ($submittedBy == 'owner') {
            $query = $query->where('user_id', $wyr->host);
        } elseif ($submittedBy == 'participants') {
            $query = $query->where('user_id', '<>', $wyr->host);
        }

        return $query->get();
    }

    public static function matchingAnswersByUser(User $user)
    {
        $answerIds = UserAnswer::where('user_id', $user->id)
            ->pluck('answer_id');

        return UserAnswer::select('user_id', 'answer_id')
            ->with(['user', 'answer'])
            ->whereIn('answer_id', $answerIds)
            ->get();
    }
}
