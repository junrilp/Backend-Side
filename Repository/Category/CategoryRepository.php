<?php

namespace App\Repository\Category;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryRepository implements CategoryInterface
{
    /**
     * This will be the logic for adding new category
     * @param string $name
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function postCategory(string $name)
    {
        $category = Category::whereRaw('(LOWER(`name`) = "' . strtolower($name) . '")')
            ->exists();

        if ($category) {
            return false;
        }

        return Category::create([
            'name' => ucwords($name)
        ]);
    }

    /**
     * This will be the logic for updating category
     * @param string $name
     * @param int $id
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function updateCategory(string $name, int $id)
    {
        $category = Category::whereRaw('(LOWER(`name`) = "' . strtolower($name) . '" AND id != "' . $id . '")')
            ->exists();

        if ($category) {
            return false;
        }

        Category::whereId($id)
            ->update([
                'name' => ucwords($name)
            ]);

        return Category::find($id);
    }

    /**
     * This will be the logic for removing category
     * @param int $id
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public static function removeCategory(int $id)
    {
        $category = Category::whereId($id);

        if (!$category->exists()) {
            return false;
        }

        $holdCategory = $category->first();
        $category->delete();

        return $holdCategory;
    }
}
