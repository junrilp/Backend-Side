<?php

namespace App\Repository\WYR;

use App\Models\Games\Invitation;
use App\Models\Games\Session;
use App\Models\Games\WYR\Answer;
use App\Models\Games\WYR\WYR;
use App\Models\User;

interface WYRInterface
{

    public static function getHost($userId);

    public static function createWYRSession($hostId, $wyrId);

    public static function storeWYR($data);

    public static function deleteWYR(WYR $wyr);

    public static function leaveWYRSession($wyrId, $userId);

    public static function createSessionParticipants($participant);

    public static function getParticipants(WYR $wyr, $data);

    public static function getAllSessions($data);

    public static function inviteUser($data);

    public static function submitQuestions($data);

    public static function acceptInvitation(Invitation $invitation);

    public static function joinSession(WYR $wyr, User $user);

    public static function submitAnswer($data, Answer $answer);

    public static function matchingAnswersByUser(User $user);

    public static function updateWYR(WYR $wyr, $data);

    public static function approveQuestion($answerSet);

    public static function declineQuestion($answerSet);

    public static function getQuestionsBySessions(Session $session, $submittedBy);
}
