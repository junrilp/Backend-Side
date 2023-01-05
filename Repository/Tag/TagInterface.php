<?php

namespace App\Repository\Tag;

use Illuminate\Http\Request;

interface TagInterface
{
    public static function postTag(string $label);

    public static function updateTag(string $label, int $id);

    public static function removeTag(int $id);
    
}