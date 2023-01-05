<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Http\Resources\PlanResourceCollection;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = (int)$request->get('perPage', (new Plan)->getPerPage());
        $plans = Plan::query()->scopes(['active'])->paginate($perPage);

        return PlanResourceCollection::make($plans);
    }

    /**
     * Display the specified resource.
     *
     * @param int $plan
     * @return \Illuminate\Http\Response
     */
    public function show(Plan $plan)
    {
        $plan->load('billingCycles');
        return PlanResource::make($plan);
    }
}
