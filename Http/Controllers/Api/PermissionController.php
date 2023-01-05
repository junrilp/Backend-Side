<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class PermissionController extends Controller
{
    use ApiResponser;

    public function index()
    {
        try {
            return $this->successResponse(Ability::all());
        } catch (Throwable $e) {
            return $this->errorResponse('Unable to retrieve Permissions.', Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(Request $request)
    {

        $rules = [
            'name' => 'required',
        ];

        $this->validate($request, $rules);

        $fields = $request->all();

        $ability = Ability::create($fields);

        return $this->successResponse($ability);

    }


    /**
     * Return role
     * @return Illuminate\Http\Response
     */
    public function show($ability)
    {

        $ability = Ability::findOrFail($ability);

        return $this->successResponse($ability);

    }

    /**
     * update role
     * @return Illuminate\Http\Response
     */
    public function update(Request $request, $ability)
    {

        $rules = [
            'name' => 'required',
        ];

        $this->validate($request, $rules);

        $ability = ability::findOrfail($ability);

        $ability->fill($request->all());

        $ability->save();

        return $this->successResponse($ability);

    }


}
