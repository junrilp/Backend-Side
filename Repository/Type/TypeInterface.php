<?php

namespace App\Repository\Type;

interface TypeInterface
{
    /**
     * @param string $name
     * @return mixed
     */
    public static function addType(string $name);

    /**
     * @param string $name
     * @param int $id
     * @return mixed
     */
    public static function updateType(string $name, int $id);

    /**
     * @param int $id
     * @return mixed
     */
    public static function removeType(int $id);
    
}