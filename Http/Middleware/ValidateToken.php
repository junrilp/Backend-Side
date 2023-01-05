<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class ValidateToken
{


    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (empty($user->password) && Route::currentRouteName() !== 'password-creation' && Route::currentRouteName() !== 'change-credential-mobile' && Route::currentRouteName() !== 'logout') {
                if (Route::currentRouteName() == 'change-credential-mobile') {
                    return redirect()->route('change-credential-mobile', [
                        'validationToken' => $user->validate_token,
                        'appMobileUrl' => env('APP_MOBILE_URL')
                    ]);
                }
                if (Route::currentRouteName() == 'password-creation') {
                    return redirect()->route('password-creation', [
                        'validationToken' => $user->validate_token
                    ]);
                }
            }
        }

        return $next($request);
    }
}
