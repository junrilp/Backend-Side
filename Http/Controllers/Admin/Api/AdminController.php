<?php

namespace App\Http\Controllers\Admin\Api;

use Throwable;
use Carbon\Carbon;

use App\Models\Note;
use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Models\Media;
use App\Models\AdminOtp;
use App\Forms\AdminOtpForm;
use App\Enums\ReportType;
use App\Enums\UserStatus;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\GroupResource;
use App\Repository\Admin\AdminUserRepository;
use App\Repository\Sms\SmsRepository;
use App\Http\Resources\ProfileResource;
use App\Jobs\SendUserRegistrationEmail;
use App\Scopes\AccountNotSuspendedScope;
use App\Repository\Profile\ProfileRepository;
use App\Http\Requests\AdminCheckProfileRequest;
use App\Http\Requests\CheckExistenceRequest;
use App\Repository\Users\UserRepository;
use App\Http\Resources\AdminUserSummaryDetailResource;
use App\Http\Resources\AdminAdminSummaryDetailResource;
use App\Http\Resources\AdminUserCompleteDetailResource;


class AdminController extends Controller
{
    use ApiResponser;

    private $smsRepository;

    public function __construct(AdminUserRepository $adminUserRepository, SmsRepository $smsRepository)
    {
        $this->adminUserRepository = $adminUserRepository;
        $this->smsRepository = $smsRepository;
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index(Request $request)
    {

        $admins = User::has('adminRoles')->with('adminRoles')->paginate();

        return $this->successResponse(AdminAdminSummaryDetailResource::collection($admins), null, Response::HTTP_OK, true);
    }

    /**
     * @param mixed $id
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function show($id)
    {

        $admin = User::with('adminRoles')->find($id);

        return $this->successResponse(new AdminUserCompleteDetailResource($admin));
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function setRole(Request $request)
    {

        $user = auth()->user()->load('adminRoles');

        if ($user->adminRoles[0]->label != 'super_administrator') {
            return $this->errorResponse('no-access', Response::HTTP_BAD_REQUEST);
        }

        $user = User::findOrfail($request->id);

        $user->assignAdminRole('administrator', auth()->id());

        return $this->successResponse(new AdminUserSummaryDetailResource($user));
    }

    public function getGroupsByUser($id)
    {
        $user = User::findOrfail($id);

        $userCreatedGroups = Group::where('user_id', $user->id)
            ->paginate();

        return $this->successResponse($userCreatedGroups);
    }

    public function setNotes(Request $request, $type)
    {
        $this->reportValidator($request, $type);

        $data = $request->all();

        $data['reporter_id'] = authUser()->id;

        $model = $this->assignReportByModel($data, $type);

        return $this->successResponse($model['response']->resource, null, Response::HTTP_CREATED);
    }

    private function reportValidator(Request $request, $type)
    {
        $typesRule = '';

        if ($type == 'users') {
            $typesRule = sprintf(
                'in:%s,%s,%s,%s,%s',
                ReportType::SUSPEND,
                ReportType::UNSUSPEND,
                ReportType::INVITE,
                ReportType::UNINVITE,
                ReportType::GENERAL
            );
        }

        if ($type == 'events' || $type == 'groups') {
            $typesRule = sprintf(
                'in:%s',
                ReportType::GENERAL
            );
        }

        $rules = [
            'modelId' => 'required',
            'note' => 'required',
            'type' => ['required', $typesRule],
            'mediaId' => 'integer',
        ];

        $request->validate($rules);
    }

    private function assignReportByModel($data, $type)
    {
        $model = [];

        if ($type == 'users') {
            $model['object'] = User::findOrfail($data['modelId']);
            $model['response'] = new AdminUserSummaryDetailResource($model['object']);
        } elseif ($type == 'events') {
            $model['object'] = Event::findOrfail($data['modelId']);
            $model['response'] = new EventResource($model['object']);
        } elseif ($type == 'groups') {
            $model['object'] = Group::findOrfail($data['modelId']);
            $model['response'] = new GroupResource($model['object']);
        }

        return $model;
    }

    public function addNoteImage(Request $request, $id)
    {
        $this->addReportImageValidator($request);

        $note = Note::findOrfail($id);
        $note->media_id = $request->get('mediaId');
        $note->save();

        return $this->successResponse($note, 'Report attachment added.');
    }

    public function removeNoteImage(Request $request, $id)
    {
        $this->addReportImageValidator($request);

        $note = Note::findOrfail($id);
        $note->media_id = null;
        $note->save();

        $media = Media::find($request->get('mediaId'));
        $media->delete();

        return $this->successResponse($media, 'Report attachment removed.');
    }

    private function addReportImageValidator(Request $request)
    {
        $rules = [
            'mediaId' => 'required|integer',
        ];

        $request->validate($rules);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function unsetRole(Request $request)
    {

        $user = auth()->user()->load('adminRoles');

        if ($user->adminRoles[0]->label != 'super_administrator') {
            return $this->errorResponse('no-access', Response::HTTP_BAD_REQUEST);
        }

        $user = User::findOrfail($request->id);

        $user->adminRoles()->detach();

        return $this->successResponse($user);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function setPassword(Request $request)
    {

        $user = auth()->user();

        $this->validate($request, [
            'password' => 'required|min:6',
        ]);

        $roleLabel = $user->adminRoles[0]->label;

        $roleId = $user->adminRoles[0]->pivot->role_id;

        $user = User::findOrfail(auth()->id());

        $user->assignRoleWithPassword($roleLabel, Hash::make($request->password), auth()->id());

        return $this->successResponse(new AdminUserSummaryDetailResource($user));
    }

    /**
     * checkExistence checks existence of email on the system
     *
     * Checks if the email is already on the system as unpublished user
     *
     * @param CheckExistenceRequest $request request with email input
     * @return Response
     **/
    public function checkExistence(Request $request)
    {

        $email = $request->email;
        $data = UserRepository::getUserByEmailOrUserName($email);

        if (!$data) {
            return $this->errorResponse('User not found', Response::HTTP_NOT_FOUND);
        }

        if ($data->status != 0) {
            return $this->successResponse(null, 'Registered user');
        }

        return $this->errorResponse('Not verified', Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     *
     * @return Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function adminVerifyOtp(Request $request)
    {
        $this->validate($request, [
            'otp' => 'required|min:6',
        ]);

        $validateOtp = (new AdminOtp)->validateOtp($request->uid, $request->otp);
        if ($validateOtp) {
            (new AdminOtp)->setOtpUsed($request->uid, $request->otp);

            return $this->successResponse($validateOtp->toArray(), 'success');
        }

        return $this->errorResponse('OTP not found', Response::HTTP_BAD_REQUEST);
    }

    public function adminLogin(Request $request)
    {

        $user = auth()->user();

        $this->validate($request, [
            'password' => 'required|min:6',
        ]);

        $roleLabel = $user->adminRoles[0]->label;

        if (Hash::check($request->password, $user->adminRoles[0]->pivot->password)) {

            $request->session()->put('is_admin', 'true');

            return $this->successResponse(new AdminUserSummaryDetailResource($user), 'success');

        }

        $request->session()->forget('is_admin');

        return $this->errorResponse('login failed', Response::HTTP_BAD_REQUEST);

    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     * @author Robert Edward Hughes Jr <robert.h@ragingriverict.com>
     */
    public function adminPanelLogin(Request $request)
    {
        try {
            $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)
                ->where('user_name', $request->user_name)
                ->orWhere('email', $request->user_name)
                ->first();

            if (!$user) {
                return $this->errorResponse('This user does not exist or the information submitted is incorrect.', Response::HTTP_NOT_FOUND);
            }

            if ($user->isAccountSuspended) {
                return $this->errorResponse('Your account has been suspended, please contact support for further information.', Response::HTTP_FORBIDDEN);
            }

            $login = $this->adminUserRepository->loginAccount(
                $request->user_name,
                $request->password,
                $request->remember
            );

            if ($login) {
                $userResource = new UserResource($login['user']);

                // Generate OTP for Admin Login
                $getAdminOtp = (new AdminOtp)->generateOTP($userResource->id);
                AdminOtpForm::mail($userResource, $getAdminOtp);

                return $this->successResponse([
                    'token' => $login['token'],
                    'user' => $userResource
                ]);
            }

            return $this->errorResponse('Enter a valid password for this login', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            throw $e;
        }
    }


    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function adminLogout(Request $request)
    {

        $request->session()->forget('is_admin');

        return $this->successResponse('Successfully Logout');
    }

    /**
     * Suspend a user account
     *
     * @param Request $request
     *
     * @return
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function suspendUserAccount(Request $request)
    {
        $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)
            ->findOrfail($request->id);
        $user->suspended_at = $request->isSuspended ? now() : null;
        $user->save();

        return $this->successResponse($user);
    }

    public function setHpInfluencer(Request $request)
    {
        $user = User::findOrfail($request->id);
        $user->hp_influencer = $user->hp_influencer === 0 ? 1 : 0;
        $user->save();
        return $this->successResponse($user);
    }

    public function updateUserProfile(AdminCheckProfileRequest $request)
    {
        // TODO: Add request validations
        try {

            $user = User::findOrfail($request->id);

            if (isset($request->email) && $request->email !== $user->email) {

                $validateToken = Str::random(60);

                $user->email = $request->email;
                $user->email_updated_at = Carbon::now();
                $user->validate_token = $validateToken;
                $user->status = UserStatus::NOT_VERIFIED;

                if (config('app.env') != 'testing') {
                    SendUserRegistrationEmail::dispatch($user->toArray($request), $validateToken)->onQueue('high');
                }
            }
            if (isset($request->mobile_number) && $request->mobile_number !== $user->mobile_number) {
                $mobileNumber = Str::replace('-', '', $request->mobile_number);

                $user->mobile_number = $mobileNumber;
                $user->mobile_number_updated_at = Carbon::now();

                $ipAddress = $request->ip();

                // TODO: Skipping sms sending in tests for now, to be discussed later
                if (config('app.env') != 'testing') {
                    $this->smsRepository->sendPhoneSMSVerification($mobileNumber, $ipAddress);
                }
            }

            $user->save();

            ProfileRepository::updateProfile($request->id, $request->except(['email', 'mobile_number']));

            //return only the updated values
            return $this->successResponse(
                (new ProfileResource($user))
                    ->returnFields(
                        array_keys($request->all())
                    )
            );
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    public function softDeleteUser($id)
    {
        $user = User::destroy($id);
        return $this->successResponse($user);
    }

    public function setTestAccount(Request $request)
    {
        $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)
            ->findOrfail($request->id);
        $user->is_case = $request->is_case;
        $user->save();

        $dataLogs['admin_logs'] = 'admin_logs';
        $dataLogs['action'] = 'setTestAccount';
        $dataLogs['auth_user'] = auth()->user()->user_name;
        $dataLogs['user'] = $user->user_name;

        return $this->successResponse($user);
    }
}
