<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSearchResource;
use App\Models\Favorite;
use App\Models\User;
use App\Repository\Favorite\FavoriteRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\FavoriteRequest;

class FavoriteController extends Controller
{
 use ApiResponser;

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function store(FavoriteRequest $request)
    {
        try {

            $userId = $request->user()->id;

            $favorite = Favorite::updateOrCreate(
                ['from_user_id' => $userId, 'to_user_id' => $request->to_user_id],
                ['is_like' => $request->is_like]
            );

            return $this->successResponse($favorite, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::alert($e);
            return $this->errorResponse('Sorry we are unable to add to favorites. Please try again.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getFavoritedMe(Request $request)
    {

        $userId = $request->user()->id;

        try {

            $favoritedMe =  FavoriteRepository::favoritedMe($userId);

            return $this->successResponse(UserSearchResource::collection($favoritedMe), '', Response::HTTP_OK, true);

        } catch (\Exception $e) {
            Log::alert($e);
            return $this->errorResponse('Failed to get favorited me.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function getMyFavorites(Request $request)
    {

        $userId = $request->user()->id;

        try {

            $myFavorites =  FavoriteRepository::myFavorites($userId);

            return $this->successResponse(UserSearchResource::collection($myFavorites), '' , Response::HTTP_OK, true);

        } catch (\Exception $e) {
            Log::alert($e);
            return $this->errorResponse('Failed to get my favorites', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }



}
