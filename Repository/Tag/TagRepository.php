<?php

namespace App\Repository\Tag;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class TagRepository implements TagInterface
{

    /**
     * This will be the logic for saving new tag
     * @param string $label
     * @return [type]
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function postTag(string $label)
    {
        $check = Tag::whereRaw('(LOWER(`label`) = "' . strtolower($label) . ' ")')->exists();

        if ($check) {
            return false;
        }

        return Tag::create([
            'label' => ucwords($label)
        ]);
    }

    /**
     * This will be the logic for updating tag
     * @param string $label
     * @param int $id
     * @return Array|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateTag(string $label, int $id)
    {
        $check = Tag::whereRaw('(LOWER(`label`) = "' . strtolower($label) . '" AND id != "' . $id . '")')
            ->exists();

        if ($check) {
            return false;
        }
        Tag::whereId($id)
            ->update([
                'label' => ucwords($label)
            ]);
        return Tag::find($id);
    }

    /**
     * This will be the logic for removing tag
     * @param int $id
     * @return Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function removeTag(int $id)
    {
        $tag = Tag::whereId($id);

        if (!$tag->exists()) {
            return false;
        }

        $holdTag = $tag->first();

        $tag->delete();

        return $holdTag;
    }
}
