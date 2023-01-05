<?php

namespace App\Repository\Type;

use App\Models\Type;
use Illuminate\Support\Facades\DB;

class TypeRepository implements TypeInterface
{
    /**
     * This will be the logic for adding type
     * @param string $name
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function addType(string $name)
    {
        $check = Type::whereRaw('(LOWER(`name`) = "' . strtolower($name) . '")')
            ->exists();

        if ($check) {
            return false;
        }

        return Type::create([
            'name' => strtolower($name)
        ]);
    }

    /**
     * This will be the logic for updating type
     * @param string $name
     * @param int $id
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateType(string $name, int $id)
    {
        $check = Type::whereRaw('(LOWER(`name`) = "' . strtolower($name) . '" AND id != "' . $id . '")')
            ->exists();

        if ($check) {
            return false;
        }

        Type::whereId($id)
            ->update([
                'name' => strtolower($name)
            ]);

        return Type::find($id);
    }

    /**
     * This will be the logic for deleting type
     * @param int $id
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function removeType(int $id)
    {
        $type = Type::whereId($id);

        if (!$type->exists()) {
            return false;
        }

        $holdType = $type->first();

        $type->delete();

        return $holdType;
    }
}
