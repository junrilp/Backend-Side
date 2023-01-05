<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Media;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Interest;
use App\Models\UserInterest;
use App\Forms\InterestForm;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Models\UserDiscussion;
use Illuminate\Support\Facades\DB;
use App\Helpers\CollectionPaginate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UserWithSameInterestResource;
use App\Http\Resources\InterestResource;
use App\Http\Resources\MediaInterestResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreInterestRequest;
use App\Repository\Interests\InterestRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Str;


class InterestController extends Controller
{
    use ApiResponser;

    private $interestForm;
    private $interest;

    public function __construct(
        InterestForm $interestForm,
        Interest $interest
    )
    {
        $this->interestForm = $interestForm;
        $this->interest = $interest;
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => InterestResource::collection(
                Interest::where('approved', 1)->get()
            )
        ],200);
    }

    public function getInterest()
    {
        return response()->json([
            'success' => true,
            'data' => $this->interestForm->getInterestByUserId(Auth::user()->id)
        ],200);
    }

    public function getMyInterests()
    {
        $user = User::find(Auth::id());
        $interests = $user->load('interests');

        return response()->json([
            'success' => true,
            'data' => InterestResource::collection(
                $user->interests
            )
        ],200);
    }

    /**
     * Store Interest
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

        }
        catch(\Exception $e) {

            return $this->errorResponse('Failed to save interest', 422);

        }

    }

    /**
     * @param Request $request
     * Save Interest Step 1
     * This will serve as the post request for
     * interest to user
     * @return [type]
     */
    public function userInterestWeb(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'interest' => 'required',
                // 'slug' => 'required',
                // 'image' => 'required'
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }
            DB::beginTransaction();
            $data = $this->interestForm->store($request);
            DB::commit();

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message'=>"Sorry, Duplicate interest with same user is not allowed"
                ], 422);
            }

            return $this->successResponse(new InterestResource(
                Interest::where('id', $data)->first()
            ));

        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * @param mixed $id
     *
     * @return [type]
     */
    public function show($id)
    {
        try {
            $checkInterest = $this->interest->where('id', $id)
                            ->first();
            if (!$checkInterest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interest not found'
                ], 404);
            }
            return response(['success' => true, 'data' => $this->interestForm->show($id)], 200);
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * @param Request $request
     * @param mixed $id
     *
     * @return [type]
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'interest' => 'required',
                // 'slug' => 'required'
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }

            DB::beginTransaction();
            $data = $this->interestForm->update($request, $id);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            $data = $this->interestForm->destroy($id);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function changeApproveStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'approved' => 'required',
                'interest_id' => 'required',
            ]);
            if ($validator->fails())
            {
                return response(['errors'=>$validator->errors()->all()], 422);
            }
            DB::beginTransaction();
            $data = $this->interestForm->changeApproveStatus($request);
            DB::commit();
            return $data;
        }
        catch(\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Will get featured interests to be display
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function getFeaturedInterests(){
        $data = Interest::where('is_featured',1)->get();
        return $this->successResponse(InterestResource::collection($data));
    }

    /**
     * Get all users that has same interest
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function getUserWithSameInterest(Request $request, int $interestId = 0) {
        $perPage = $request->perPage ?? 10;
        $data = UserInterest::where('interest_id', $interestId)
            ->has('user')
            ->paginate($perPage);
        return $this->successResponse(UserWithSameInterestResource::collection($data), 'Success', Response::HTTP_OK, true);
    }

    /*
     * Get all media uploaded by all users and match it via interest
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function getAllMediaByInterest(Request $request) {
        $userInterest = authUser()->interests->pluck('id'); // Get user all interest
        $perPage = $request->perPage ?? 20;
        $interest = UserInterest::query()
                ->whereIn('interest_id',$userInterest)
                ->where('user_interests.user_id', '!=', authUser()->id);

        $wallPost = (clone $interest)
                ->wallPost()
                ->get();

        $eventWallPost = (clone $interest)
                ->eventWallPost()
                ->get();

        $eventAlbumPost = (clone $interest)
                ->eventAlbumPost()
                ->get();

        $groupWallPost = (clone $interest)
                ->groupWallPost()
                ->get();

        $groupAlbumPost = (clone $interest)
                ->groupAlbumPost()
                ->get();

        $allPosts = $wallPost
                    ->merge($eventWallPost)
                    ->merge($eventAlbumPost)
                    ->merge($groupWallPost)
                    ->merge($groupAlbumPost)
                    ->sortByDesc('created_at')
                    ->values();
        $allPosts = (new CollectionPaginate($allPosts))->paginate($perPage);
        //return $this->successResponse($allPosts); // for checking
        return $this->successResponse(
            MediaInterestResource::collection($allPosts),
            'Success',
            Response::HTTP_OK,
            true
        );
    }

    public function getAllMediaByInterestAttachments(Request $request) {
        $data = $request->all();

        $user = User::find($data['user_id']);

        $media = \App\Models\Media::whereIn('id', $data['media_ids'])
            ->get();

        $attachments = [
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'entity' => $data['entity'],
            'user' => new UserResource($user),
            'attachments' => Media::collection($media)
        ];

        return $this->successResponse($attachments,
            'Success',
            Response::HTTP_OK
        );
    }
}
