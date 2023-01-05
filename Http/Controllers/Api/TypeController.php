<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Type;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use App\Http\Requests\TypeRequest;
use App\Http\Resources\TypeResource2;
use App\Http\Controllers\Controller;
use App\Repository\Type\TypeRepository;

class TypeController extends Controller
{
    use ApiResponser;

    private $type;

    public function __construct(TypeRepository $type)
    {
        $this->type = $type;
    }

    /**
     * Retrieving all the type records
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function index()
    {
        try {
            return $this->successResponse(Type::all());
        } catch (Exception $e) {
            return $this->errorResponse('Unable to retrieve types.', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Saving new type into type repository
     * @param TypeRequest $request
     * @return Object
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function store(TypeRequest $request)
    {
        $data = $this->type->addType($request->name);

        if (!$data) {
            return $this->errorResponse('Type already exist.', Response::HTTP_CONFLICT);
        }

        return $this->successResponse($data, null, Response::HTTP_CREATED);
    }

    /**
     * Update type and send request to repository
     * @param TypeRequest $request
     * @param mixed $id
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function update(TypeRequest $request, $id)
    {
        $data = $this->type->updateType($request->name, $id);

        if (!$data) {
            return $this->errorResponse('Type already exist.', Response::HTTP_CONFLICT);
        }

        return $this->successResponse($data);
    }

    /**
     * Remove Type
     * @param TypeRequest $request
     * @param mixed $id
     * @return Object|Boolean
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function destroy($id)
    {
        $data = $this->type->removeType($id);

        if (!$data) {
            return $this->errorResponse('No record found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse($data);
    }

    /**
     * Get types
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function types() {
        try {
            return $this->successResponse(TypeResource2::collection(Type::all()));
        } catch (Exception $e) {
            return $this->errorResponse('Unable to retrieve types.', Response::HTTP_BAD_REQUEST);
        }
    }
}
