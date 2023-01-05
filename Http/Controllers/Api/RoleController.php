<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

class RoleController extends Controller
{
    use ApiResponser;

    public function index()
    {
        try {
            return $this->successResponse(Role::where('category', 'event')->get());
        } catch (Throwable $e) {
            return $this->errorResponse('Unable to retrieve roles.', Response::HTTP_BAD_REQUEST);
        }
    }

    public function store(Request $request)
    {

        $rules = [
            'name' => 'required',
        ];

        $this->validate($request, $rules);

        $fields = $request->all();

        $role = Role::create($fields);

        return $this->successResponse($role);

    }


    /**
     * Return role
     * @return Illuminate\Http\Response
     */
    public function show($role)
    {

        $role = Role::findOrFail($role);

        return $this->successResponse($role);

    }

    /**
     * update role
     * @return Illuminate\Http\Response
     */
    public function update(Request $request, $role)
    {

        $rules = [
            'name' => 'required',
        ];

        $this->validate($request, $rules);

        $role = Role::findOrfail($role);

        $role->fill($request->all());

        $role->save();

        return $this->successResponse($role);

    }


}
