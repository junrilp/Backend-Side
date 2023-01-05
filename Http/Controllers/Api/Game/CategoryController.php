<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\CategoryRequest;
use App\Http\Resources\Game\CategoryResource;
use App\Models\Games\GameCategory;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use Throwable;

class CategoryController extends Controller
{
    use ApiResponser;

    public function store(CategoryRequest $request)
    {
        $data = $request->validated();

        try {
            $category = GameCategory::create([
                'name' => $data['name'],
                'cover' => $data['cover'],
                'thumbnail' => $data['thumbnail']
            ]);

            return $this->successResponse(new CategoryResource($category), null, Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function show(GameCategory $category)
    {
        return $this->successResponse(new CategoryResource($category), null, Response::HTTP_OK);
    }

    public function update(GameCategory $category, CategoryRequest $request)
    {
        $data = $request->validated();

        try {
            $category->name = $data['name'];
            $category->cover = $data['cover'];
            $category->thumbnail = $data['thumbnail'];
            $category->save();

            return $this->successResponse(new CategoryResource($category), null, Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function destroy(GameCategory $category)
    {
        try {
            $category->delete();

            return $this->successResponse(new CategoryResource($category), null, Response::HTTP_OK);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }
}
