<?php

namespace App\Repository\Event;

use App\Models\User;
use App\Models\Event;

interface EventInterface
{
    public static function getTypeAndCategory();

    public static function getEvents(int $perPage);

    public static function getEventsByUserId(int $userId);

    public static function postEvent(array $data = [], int $userId);

    public static function updateEvent(array $data = [], int $userId, int $id);

    public static function deleteEvent(int $id);

    public static function getEventBySlug(string $slug, string $state = null, string $city = null);

    public static function publishEventSlug(string $slug, int $userId);

    public static function postUserEvent(array $data = [], int $userId);

    public static function updateUserEvent(array $data = [], int $userId, int $id);

    public static function deleteUserEvent(int $id, int $userId);

    public static function getFeatureEvent();

    public function getAttendees(string $slug, array $request = [], int $perPage = 20, int $eventRSVPStatus = 0);

    public static function getOtherEvents(int $eventId);

    public static function getMyEvents(int $userId, array $request = []);

    public static function loadEvents(array $request = [], int $userId, string $type);

    public static function search($requestArray, $withLimit = false, $withPagination = false, int $perPage = 12, int $limit = 10, $isMyEvent = false, string $tab = 'interested');

    public static function setGateKeeper(int $eventId, int $userId);

    public static function mail(array $email);

    public static function setAsAttended(User $user, Event $event);

    public static function updateLimitedCapacityCount($event);


}
