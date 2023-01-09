<?php

namespace App\Http\Controllers\Admin\Api;

use Exception;
use App\Models\Note;
use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Reports;
use App\Enums\TakeAction;
use App\Enums\UserStatus;
use App\Enums\SearchMethod;
use App\Mail\ResetPassword;
use App\Models\UserProfile;
use App\Traits\AdminTraits;
use App\Enums\GeneralStatus;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Scopes\AccountNotSuspendedScope;
use App\Repository\Group\GroupRepository;
use App\Repository\Media\MediaRepository;
use App\Repository\Steps\StepsRepository;
use Illuminate\Support\Facades\Validator;
use App\Repository\Browse\BrowseRepository;
use App\Http\Requests\UpdateUserProfileRequest;
use App\Http\Resources\AdminDashboardUserResource;
use App\Http\Resources\AdminUserSummaryDetailResource;
use App\Http\Resources\AdminUserCompleteDetailResource;

class UserController extends Controller
{
    use ApiResponser;
    use AdminTraits;

    public $browseRepository;
    public $perPage;
    public $limit;
    public $withLimit;
    public $woutLimit;
    private $groupRepository;

    private $fallBack = ['keyword' => ''];


    public function __construct(BrowseRepository $browseRepository, GroupRepository $groupRepository)
    {
        $this->browseRepository = $browseRepository;
        $this->perPage = 12;
        $this->limit = 10;
        $this->withLimit = true;
        $this->woutLimit = false;
        $this->groupRepository = $groupRepository;
    }

    /**
     * list all PF users
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory AdminUserSummaryDetailResource $users
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index(Request $request)
    {
        $users = User::query()->withoutGlobalScope(AccountNotSuspendedScope::class);

        if (!empty($request->keyword)) {
            $users->whereRaw('CONCAT(first_name, " ", last_name, " ", user_name, " ", email) LIKE ?', "%{$request->keyword}%");
        }

        $users = $users
            ->with('adminRoles')
            ->paginate(10);

        return $this->successResponse(AdminUserSummaryDetailResource::collection($users), '', 200, true);
    }

    /**
     * show user model
     * @param mixed $id
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory AdminUserCompleteDetailResource $user
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function show($id)
    {
        $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)->find($id);

        return $this->successResponse(new AdminUserCompleteDetailResource($user));
    }

    // Admin

    public function delete(User $user)
    {
        Reports::where('user_id', $user->id)->where('resource', 'user')->delete();
        $user->delete();
        return $this->successResponse(new AdminUserCompleteDetailResource($user));

        return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
    }

    public function statistics()
    {
        $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)->withTrashed();
        $active = $this->userStatisticsByStatus(UserStatus::PUBLISHED);
        $deactivated = $this->userStatisticsByStatus(UserStatus::DEACTIVATED);
        $flagged = $this->userStatisticsByStatus(UserStatus::FLAGGED);
        $suspended = User::withoutGlobalScope(AccountNotSuspendedScope::class)->where('status' , GeneralStatus::SUSPENDED)->count();
        $deleted = User::withTrashed()->whereNotNull('deleted_at')->where('status' , '>' , 1)->count();
        $notVerified = User::where('status', UserStatus::NOT_VERIFIED)->count(); 
        $verified = User::where('status', UserStatus::VERIFIED)->count(); 
    
        return $this->successResponse([
            'statistics' => [
                'Active' => $active,
                'Unverified' => $notVerified,
                'Verified' => $verified,
                'Deactivated' => $deactivated,
                'Flagged' => $flagged,
                'Suspended' => $suspended,
                'Deleted' => $deleted
            ],
            'all' => $user->where('status' , '>' , 1)->count()
        ]);
    }

    public function getUser(Request $request)
    {
        try {
            $perPage = $request->perPage ?? 25;
            $userData = [];
            $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)->withTrashed()->with('profile', 'photos', 'preferences', 'reports', 'userNotes', 'badges');

            if (isset($request->type) && $request->type !== 'all') {
                if ($request->type === TakeAction::ACTIVE) {
                    $userData = $user->where('status', UserStatus::PUBLISHED)
                                    ->suspendedAndDeleted()
                                    ->paginate($perPage);
                }
                if ((int)$request->type === GeneralStatus::UNVERIFIED) {
                    $userData = $user->where('status', UserStatus::NOT_VERIFIED)
                        ->suspendedAndDeleted()
                        ->paginate($perPage);
                }
                if ((int)$request->type === GeneralStatus::VERIFIED) {
                    $userData = $user->where('status', UserStatus::VERIFIED)
                        ->suspendedAndDeleted()
                        ->paginate($perPage);
                }
                if ((int)$request->type === GeneralStatus::DEACTIVATED) {
                    $userData = $user->where('status', UserStatus::DEACTIVATED)
                                ->suspendedAndDeleted()
                                ->paginate($perPage);
                }
                if ((int)$request->type === GeneralStatus::FLAGGED) {
                    $userData = $user->where('status', UserStatus::FLAGGED)
                                ->suspendedAndDeleted()
                                ->paginate($perPage);
                   
                }
                if ($request->type === TakeAction::FLAG) {
                    $userData = $user
                        ->where('status', UserStatus::FLAGGED)
                        ->orWhere('status', UserStatus::PUBLISHED)
                        ->whereNull('deleted_at')
                        ->whereNull('suspended_at')
                        ->paginate($perPage);
                }
                if ($request->type === TakeAction::REACTIVATE) {
                    $userData = $user->inactive()->paginate($perPage);
                }   
                if ($request->type === TakeAction::DEACTIVATE) {
                    $userData = $user->whereNull('deleted_at')
                        ->whereIn('status', [GeneralStatus::PUBLISHED, GeneralStatus::FLAGGED, GeneralStatus::SUSPENDED])
                        ->paginate($perPage);
                }
                if ((int)$request->type === GeneralStatus::SUSPENDED) {
                    $userData = $user->where('status', GeneralStatus::SUSPENDED)->paginate($perPage);
                }
                if ((int)$request->type === GeneralStatus::DELETED) {
                    $userData = $user->whereNotNull('deleted_at')->paginate($perPage);
                }
            } else {
                $userData = $user->paginate($perPage);
            }
            
            return $this->successResponse(AdminDashboardUserResource::collection($userData), '', Response::HTTP_OK, true);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $userData = User::whereId($request->id);
            $password = Hash::make($request->password);
            $userData->update(['password' => $password]);

            if ($userData->first()->validTypeAccount) {
                Mail::to($request->email)->send(new ResetPassword(User::whereId($request->id)->first(), $request->password));
            }

            return $this->successResponse(new AdminDashboardUserResource($userData->first()));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function browse(Request $request)
    {
        try {
            // if ($this->isAdmin()) {
            $searchFilter = $this->browseRepository->searchFilter($request->all());

            $allMembers = $this->browseRepository->elasticSearchAdminUser(
                $searchFilter,
                $this->woutLimit,
                $this->perPage,
                $this->limit,
                SearchMethod::ALL_MEMBERS
            );

            $allMembers->load('profile', 'photos', 'preferences', 'reports', 'userNotes', 'badges');
            
            return $this->successResponse(AdminDashboardUserResource::collection($allMembers), '', Response::HTTP_OK, true);

            // return $this->successResponse($allMembers);
            // }

            // return $this->errorResponse('Unauthorized', Response::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something is happening ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function getUserProfile(Request $request)
    {
        $user = User::where('user_name', $request->user_name)->with('primaryPhoto', 'preferences', 'profile', 'interests', 'photos', 'badges')->withTrashed()->first();

        return $this->successResponse(new AdminDashboardUserResource($user));
    }

    public function updateUserProfile($id, UpdateUserProfileRequest $request)
    {
        $restrictedField = [];
        if ($request->submit_block === 'isEditedBasicInfo') {
            $restrictedField = ['first_name', 'last_name'];
        }
        if ($request->submit_block === 'whyImThePerfectFriend') {
            $restrictedField = ['about_me'];
        }
        if ($request->submit_block === 'whyImLookingPerfectFriend') {
            $restrictedField = ['what_type_of_friend_are_you_looking_for'];
        }
        if ($request->submit_block === 'eventsAndActivities') {
            $restrictedField = ['identify_events_activities'];
        }
        $restrictedWord = checkMultipleColumnForRestrictedWord($request->only($restrictedField));

        if ($restrictedWord) {
            return $this->errorResponse($restrictedWord, Response::HTTP_NON_AUTHORITATIVE_INFORMATION);
        }

        $user = User::withTrashed()->with('profile')->find($id);
        
        if (array_key_exists('interests', $request->all())) {
            $user->assignInterest($request['interests']);
        }

        $profile = UserProfile::firstOrCreate([
            'user_id' => $id,
        ]);

        // Save only fillable values
        $profileFillables = $profile->getFillable();
        $userFillables = $user->getFillable();

        $profile->fill(
            array_filter($request->all(), function ($key) use ($request, $profileFillables) {
                return in_array($key, $profileFillables);
            }, ARRAY_FILTER_USE_KEY)
        )->save();

        if (
            array_key_exists('first_name', $request->all()) or
            array_key_exists('last_name', $request->all())  or
            array_key_exists('birthdate', $request->all())  or
            array_key_exists('zodiac_sign', $request->all())
        ) {
            $user->fill($request->all(),   array_filter($request->all(), function ($key) use ($userFillables) {
                return in_array($key, $userFillables);
            }, ARRAY_FILTER_USE_KEY))->save();
        }

        return $this->successResponse(new AdminDashboardUserResource($user));
    }

    public function updateUserPreference($id, Request $request)
    {
        $fetchFillable = StepsRepository::userPreferenceForm($request->all(), $id);

        $preference = UserPreference::where('user_id', $id)
            ->update($fetchFillable);

        return $this->successResponse($preference);
    }

    public function deletePhotos($id, Request $request)
    {
        foreach ($request->image as $rows) {
            MediaRepository::deleteSingleImage($rows);
        }
        return $this->successResponse([]);
    }

    public function saveNotes(Request $request)
    {
        try {
            $data = Note::updateOrCreate(
                [
                    'notable_id' => $request->notable_id,
                    'type' => $request->notable_type
                ],
                [
                    'reporter_id' => authUser()->id,
                    'notable_type' => (($request->notable_type === 'user') ? User::class : ($request->notable_type === 'group')) ? Group::class : Event::class,
                    'notable_id' => $request->notable_id,
                    'note' => $request->note,
                    'media_id' => (!empty($request->media_id) ? $request->media_id[0]['id'] : null)
                ]
            );
            return $data;
        } catch (Exception $e) {
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function searchHeader(Request $request)
    {
        try {
            $perPage = $request->perPage ?? $this->perPage;
            $users = User::with('primaryPhoto', 'preferences', 'profile', 'interests', 'photos', 'badges');

            if ($request->search_field !== 'null' && $request->search_field !== null) {
                $data['keyword'] = $request->search_field;
                $searchFilter = $this->browseRepository->searchFilter($data);
                
                $users = $this->browseRepository->elasticSearchAdminUser(
                    $searchFilter,
                    $this->woutLimit,
                    $this->perPage,
                    $this->limit,
                    SearchMethod::ALL_MEMBERS
                );

                
                // $users->searchByPartialName($request->search_field);
            }

            if ($request->city_state !== 'null' && $request->city_state !== null) {
                $cityState = $request->city_state;
                $users->searchProfileCityState($cityState);
            }

            $paginateUser = $users->paginate($perPage);

            return $this->successResponse(AdminDashboardUserResource::collection($paginateUser), '', Response::HTTP_OK, true);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    private function userStatisticsByStatus($status)
    {
        return User::where('status', $status)->count();
    }
}
