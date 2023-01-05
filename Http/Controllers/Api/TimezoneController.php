<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Timezone;
use App\Http\Resources\TimezoneResource;
use App\Traits\ApiResponser;

class TimezoneController extends Controller
{
    use ApiResponser;

    public function index()
    {
        $timezones = Timezone::orderBy('offset')->get();

        return $this->successResponse(TimezoneResource::collection($timezones));
    }
}
