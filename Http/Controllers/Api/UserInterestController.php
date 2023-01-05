<?php

namespace App\Http\Controllers\Api;

use App\Traits\ApiResponser;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterestRequest;
use App\Repository\Interests\InterestRepository;


class UserInterestController extends Controller
{
    use ApiResponser;
    /**
     * Store a newly created resource in storage.
     * @param StoreInterestRequest $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Mark Anthony Tableza <mark.t@ragingriverict.com>
     */
    public function store(StoreInterestRequest $request)
    {

       try {
            $interest = InterestRepository::store($request->interest);

            return $this->successResponse($interest);

        } catch (\Exception $e) {

            return $this->errorResponse('Failed to save interest', 422);

        }


    }

}
