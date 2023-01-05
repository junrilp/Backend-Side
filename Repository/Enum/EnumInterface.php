<?php

namespace App\Repository\Enum;

use Illuminate\Http\Request;

interface EnumInterface
{
    public static function getEnum(
        int $data,
        string $enum
    );

    public static function getEnums(
        array $data,
        string $enum
    );
}