<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Category;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource2;
use App\Repository\Category\CategoryRepository;

class CategoryController extends Controller
{
    use ApiResponser;

    private $category;

    public function __construct(CategoryRepository $category)
    {
        $this->category = $category;
    }

    /**
     * Retrieve all category
     * @return Object $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index()
    {
        try {
            return $this->successResponse(Category::all());
        } catch (Exception $e) {
            return $this->errorResponse('Unable to retrieve category.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Will update the category using repository
     * @param CategoryRequest $request
     * @return Object $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(CategoryRequest $request)
    {
        $data = $this->category->postCategory($request->name);

        if (!$data) {
            return $this->errorResponse('Category already exist.', Response::HTTP_CONFLICT);
        }

        return $this->successResponse($data, null, Response::HTTP_CREATED);
    }

    /**
     * Will update the category using repository
     * @param CategoryRequest $request
     * @param mixed $id
     * @return Array $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(CategoryRequest $request, $id)
    {
        $data = $this->category->updateCategory($request->name, $id);

        if (!$data) {
            return $this->errorResponse('Category already exist.', Response::HTTP_CONFLICT);
        }

        return $this->successResponse($data);
    }

    /**
     * Delete category
     * @param mixed $id
     * @return Array $data
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy($id)
    {
        $data = $this->category->removeCategory($id);

        if (!$data) {
            return $this->errorResponse('No record found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse($data);
    }

    /**
     * Get categories
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function categories() {
        try {
            return $this->successResponse(CategoryResource2::collection(Category::all()));
        } catch (Exception $e) {
            return $this->errorResponse('Unable to retrieve category.', Response::HTTP_BAD_REQUEST);
        }
    }
}
