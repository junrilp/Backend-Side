<?php

namespace App\Http\Controllers\Api;


use Exception;
use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use App\Http\Resources\Media;
use Illuminate\Http\Response;
use App\Forms\RegistrationForm;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Jobs\SendUserRegistrationEmail;
use App\Repository\Users\UserRepository;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\RegistrationRequest;
use App\Http\Requests\CheckExistenceRequest;
class RegisteredUserController extends Controller
{
    use ApiResponser;
    private $userRepository;
    private $registrationForm;

    public function __construct(
        UserRepository $userRepository,
        RegistrationForm $registrationForm
    ) {
        $this->userRepository = $userRepository;
        $this->registrationForm = $registrationForm;
    }

    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function generateUserName(Request $request)
    {
        return response([
            'userName' =>  $this->registrationForm->generateUserName()
        ]);
    }

    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->registrationForm->store($request);
            DB::commit();
            return $data;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function storeWeb(RegistrationRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->registrationForm->storeWeb($request);
            DB::commit();

            return $data;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }



    /**
     * Will used this as checker for correct token sent from email
     * If browser is mobile it will redirect into android or ios app installed
     * If browser is web it will redirect into Vue components
     *
     * @param Request $request
     * @return redirection
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function validateAccount(Request $request)
    {

        $data = $this->userRepository->validateAccount($request->id, UserStatus::VERIFIED);

        $agent = new \Jenssegers\Agent\Agent;

        $isMobile = $agent->isMobile();

        if ($data) {

            Auth::login($data);

            if ($isMobile) {

                return redirect()->route('change-credential-mobile', [
                    'validationToken' => $request->id
                ]);
            } else {

                return redirect()->route('password-creation', [
                    'validationToken' => $request->id
                ]);
            }
        } else {
            return redirect()->route('token-invalid', []);
        }
    }

    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function activateAccount(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_name'         => ['required'],
                'password'          => ['required', 'min:6'],
                'validate_token'   => ['required']
            ]);
            if ($validator->fails()) {
                return response(['errors' => $validator->errors()->all()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            DB::beginTransaction();
            $data = $this->registrationForm->activateAccount($request);
            DB::commit();
            return $data;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * @param Request $request
     *
     * @return [type]
     */
    public function uploadPhoto(Request $request)
    {
        try {
            DB::beginTransaction();
            $data = $this->registrationForm->uploadPhoto($request);
            DB::commit();
            return response()->json([
                'success' => true,
                'data' => Media::collection(
                    User::with('photos')->find(authUser()->id)->photos
                ),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * checkExistence checks existence of email on the system
     *
     * Checks if the email is already on the system as unpublished user
     *
     * @param CheckExistenceRequest $request request with email input
     * @return Response
     **/
    public function checkExistence(CheckExistenceRequest $request) {

        $email = $request->email;
        $data = UserRepository::getUserByEmail($email);

        if(!$data) {
            return $this->successResponse([], 'No found user');
        }

        if($data->status != 0) {
            return $this->successResponse(null, 'Registered user');
        }

        return $this->successResponse([
            'status' => $data->status,
            'email' => $email
        ], 'Not verified');
    }

    /**
     * resendValidationEmail
     *
     * Resends the validation email
     *
     * @param CheckExistenceRequest $request request input
     * @return Response
     **/
    public function resendValidationEmail(CheckExistenceRequest $request) {
        $alreadyVerified = User::query()
            ->where('email', $request->email)
            ->whereIn('status', [UserStatus::VERIFIED, UserStatus::PUBLISHED])
            ->exists();
        if($alreadyVerified) {
            throw new Exception('Account with this email address does not exists or already verified.');
        }

        if(
            $request->has('update_email_token') &&
            ($updateUserEmailToken = $request->post('update_email_token')) &&
            Cache::has($updateUserEmailToken)
        ) {
            $user = $this->userRepository->getUserByChangeEmailToken($updateUserEmailToken);
            $this->userRepository->updateUserEmail($user, $request->email);
            $email = $user->email;
            Cache::forget($updateUserEmailToken);
        } else {
            $email = $request->email;
            $user = UserRepository::getUserByEmail($email);
        }

        if(!$user) {
            return $this->successResponse([], 'No found user', Response::HTTP_NOT_FOUND);
        }

        //issue a new token
        $validateToken = Str::random(60);
        $user->update([
            'validate_token' => $validateToken
        ]);

        SendUserRegistrationEmail::dispatch($user->toArray($request), $validateToken)->onQueue('high');
        return $this->successResponse([
            'email' => $email,
            'change_email_token' => RegistrationForm::generateChangeEmailToken(User::find($user->id)),
        ], 'Validation e-mail sent. Please check your email.');
    }

    public function update(Request $request, User $user)
    {
        $this->userRepository->updateRegistrationDetails($user, [
            'emailAddress' => $request->email,
            'firstName' => $request->first_name,
            'lastName' => $request->last_name,
            'birthdate' => $request->birthdate,
            'zodiacSign' => $request->zodiac_sign,
            'photoId' => $request->photo_id,
            'accountType' => $request->account_type,
        ]);

        return $this->successResponse([], 'Successfully updated the user', Response::HTTP_NO_CONTENT);
    }
}
