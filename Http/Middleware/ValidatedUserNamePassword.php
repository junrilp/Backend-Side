<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Closure;
use App\Providers\RouteServiceProvider;
use App\Models\User;

class ValidatedUserNamePassword
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
        $user = Auth::user();
        if (Auth::check() && $user->status !== UserStatus::NOT_VERIFIED) {
            if (!empty($user->user_name) && !empty($user->password) && Route::currentRouteName() == 'password-creation') {
                if ($user->status !== UserStatus::PUBLISHED) {
                    return redirect('profile-builder/step-1');
                } else {
                    return redirect(RouteServiceProvider::HOME);
                }
            }
        }

        return $next($request);
    }
}
