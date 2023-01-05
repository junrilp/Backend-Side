<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

use Illuminate\Http\Request;
use Carbon\Carbon;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;

use App\Forms\AuthenticationForm;
use App\Forms\LoginForm;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repository\Users\UserRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Enums\DefaultPageType;
use App\Scopes\AccountNotSuspendedScope;
use App\Http\Requests\VerifyEmailUsernameRequest;

class LoginController extends Controller
{
    use ApiResponser;

    private $authenticationForm;
    private $userRepository;

    public function __construct(
        UserRepository $userRepository,
        AuthenticationForm $authenticationForm
    ) {
        $this->userRepository = $userRepository;
        $this->authenticationForm = $authenticationForm;
    }

    /**
     * Login user
     * @param Request $request
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function login(Request $request)
    {


        try {
            $validator = Validator::make($request->all(), [
                'user_name' => 'required',
                'password' => 'required'
            ]);
            if ($validator->fails()) {
                return response(['errors' => $validator->errors()->all()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            /*
            * if the validator will turn tru
            * just pass the request to authenticationForm in login method
            */

            //Replaced with Resource
            //return $this->authenticationForm->login($request);

            /*
            * Sample Implementaion for Using Resource
            * uncomment line 89 to go back to previous code
            */
            $checkEmailUserName = User::where('user_name', $request->user_name)
                ->orWhere('email', $request->user_name)
                ->exists();

            if (!$checkEmailUserName) {
                $this->errorResponse('This user does not exist or the information submitted is incorrect.', Response::HTTP_NOT_FOUND);
            }

            return LoginForm::login($request);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param LoginRequest $request
     *
     * @return [type]
     */
    public function loginAccount(LoginRequest $request)
    {
        try {
            /*
        * Sample Implementaion for Using Resource
        * uncomment line 89 to go back to previous code
        */
            $user = User::withoutGlobalScope(AccountNotSuspendedScope::class)
                    ->where('user_name', $request->user_name)
                    ->orWhere('email', $request->user_name)
                    ->first();

            if (!$user) {
                return $this->errorResponse('This user does not exist or the information submitted is incorrect.', Response::HTTP_NOT_FOUND);
            }

            // check if account is suspended
            if ($user->isAccountSuspended){
                return $this->errorResponse('Your account has been suspended, please contact support for further information.', Response::HTTP_FORBIDDEN);
            }

            $login = $this->userRepository->loginAccount(
                $request->user_name,
                $request->password,
                $request->remember
            );

            if ($login) {

                $user = User::findOrFail($login['user']['id']);

                $userResource = new UserResource($user);

                //TODO log or record user activity for recently active user
                $user->last_login_at = Carbon::now()->toDateTimeString();
                $user->save();

                $result = [
                    'token' => $login['token'],
                    'user' => $userResource,
                    'step' => $login['step'],
                ];

                return response()->json([
                    'success' => true,
                    'data' => $result
                ]);

            }

            return $this->errorResponse('Enter a valid password for this login', Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Logout user
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function Logout(Request $request)
    {
        try {
            if ($request->bearerToken()) {
                $bearerToken = $request->bearerToken();
                $tokenId = (new Parser(new JoseEncoder()))->parse($bearerToken)->claims()->all()['jti'];
                DB::table('oauth_access_tokens')
                    ->where('id', $tokenId)
                    ->delete();
            }
            Session::flush();
            return response()->json([
                'success' => true,
                'message' => 'You have been successfully logged out!'
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * @param VerifyEmailUsernameRequest $request
     * 
     * @return JsonResponse
     */
    public function verifyEmailUsername(VerifyEmailUsernameRequest $request): JsonResponse
    {
        $status = UserRepository::verifyEmailUsername($request->username);

        return $this->successResponse([
            'status' => $status
        ]);
    }
}
