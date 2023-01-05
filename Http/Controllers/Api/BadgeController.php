<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BadgeRequest;
use App\Http\Resources\BadgeResource;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BadgeController extends Controller
{
    use ApiResponser;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $perPage = $request->perPage ?? 20;
        $data = Badge::where('user_id', authUser()->id)
            ->simplePaginate($perPage);
        return $this->successResponse(BadgeResource::collection($data), 'success', Response::HTTP_OK, true);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(BadgeRequest $request)
    {
        $data = Badge::create([
            'user_id' => authUser()->id,
            'name' => $request->name,
            'description' => $request->description,
            'amount' => $request->amount,
            'admin_fee' => $request->admin_fee,
            'badge_type' => $request->badge_type,
            'is_featured' => $request->is_featured,
            'media_id' => $request->media_id,
        ]);
        return $this->successResponse(new BadgeResource($data));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Badge $badge)
    {
        return $this->successResponse(new BadgeResource($badge));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(BadgeRequest $request, Badge $badge)
    {
        $badge->update([
            'name' => $request->name,
            'description' => $request->description,
            'amount' => $request->amount,
            'admin_fee' => $request->admin_fee,
            'badge_type' => $request->badge_type,
            'is_featured' => $request->is_featured,
            'media_id' => $request->media_id,
        ]);

        return $this->successResponse(new BadgeResource($badge));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Badge::where('id', $id)
            ->where('user_id', authUser()->id)
            ->delete();
        return $this->successResponse(null);
    }

    /**
     * Search badges
     *
     */
    public function searchlists(Request $request)
    {
        $badge = Badge::query();
        $perPage = $request->perpage ?? 20;

        if ($request->has('badge_type')) {
            $badge->where('badge_type', $request->badge_type);
        }

        if ($request->has('keyword')) {
            $keyword = $request->keyword;
            $badge->whereRaw("name LIKE ?", ["%{$keyword}%"]);
        }

        if ($request->has('is_featured')) {
            $badge->where('is_featured', $request->is_featured);
        }

        $data = $badge->simplePaginate($perPage);
        return $this->successResponse(BadgeResource::collection($data), 'success', Response::HTTP_OK, true);
    }

    /*
     * Add Badge to user
     */
    public function addUserBadge(Request $request)
    {
        $userBadge = UserBadge::firstOrCreate([
            'user_id' => $request->user_id,
            'badge_id' => $request->badge_id,
        ])->load('badge');
        return $this->successResponse(new BadgeResource($userBadge->badge));
    }

    /*
     * Remove user badge
     */
    public function removeUserBadge(Request $request)
    {
        UserBadge::where('user_id', $request->user_id)
            ->where('badge_id', $request->badge_id)
            ->delete();
        return $this->successResponse(null);
    }
}
