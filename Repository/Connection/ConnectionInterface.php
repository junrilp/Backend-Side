<?php

namespace App\Repository\Connection;

interface ConnectionInterface
{

    public function getBirthdayCelebrants(array $request);

    public function getEventConnection(array $request);

    public function getGroupConnection(array $request, string $birthdate);

    public function dateFilter(string $date);



}
