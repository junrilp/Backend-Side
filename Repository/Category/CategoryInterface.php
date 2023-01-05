<?php

namespace App\Repository\Category;

use Illuminate\Http\Request;

interface CategoryInterface
{
    public static function postCategory(string $name);

    public static function updateCategory(string $name, int $id);

    public static function removeCategory(int $id);
}