<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\User;
use Illuminate\Http\Response;
use App\Traits\ApiResponser;
use Carbon\Carbon;
use App\Http\Resources\UserResource;
use App\Repository\Users\UserRepository;

class AuthenticatedSessionController extends Controller
{
    use ApiResponser;

    /**
     * Display the login view.
     *
     * @return \Inertia\Response
     */
    public function create()
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(UserRepository $userRepository, LoginRequest $request)
    {
        // $request->authenticate();

        // $request->session()->regenerate();

        // return redirect()->intended(RouteServiceProvider::HOME);

        try {
            //TODO: handle redirection here instead of returning a JSON

            $request->session()->keep(['greetBday']);

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

            $login = $userRepository->loginAccount(
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
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
