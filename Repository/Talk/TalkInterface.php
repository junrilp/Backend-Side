<?php

namespace App\Repository\Talk;

use App\Models\Talk;

interface TalkInterface
{
    public function all(int $pageSize = 20);

    public function get();

    public function getByOwnerId(int $ownerId, int $pageSize = 20);

    public function create(array $talkDetails);

    public function update(int $talkId, int $ownerId, array $talkDetails);

    public function delete(int $talkId, int $ownerId);

    public function join(int $talkId, int $userId);

    public function addAttendees(int $talkId, int $ownerId, array $userIds);

    public function removeAttendee(int $talkId, int $ownerId, int $userId);

    public function stop(int $talkId, int $ownerId);

    public function allowAttendeeToUnmute(Talk $talkId, int $ownerId, int $attendeeId, bool $allow, bool $toggle);

    public function sendMessage(int $talkId, int $attendeeId, string $message);

    public function getAttendees(int $talkId, int $ownerId, int $pageSize = 20);

    public function validateBroadcast(string $streamId);
}
