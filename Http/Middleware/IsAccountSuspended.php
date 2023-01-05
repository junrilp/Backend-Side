<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Session;

class IsAccountSuspended
{
    /**
     * Handle an incoming request.
     * Check if the user is logged-in and suspended that goes to a certain page
     * User will flush the session and redirected to the suspension page
     *
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // check if account is suspended
        if (authCheck() && authUser()->isAccountSuspended) {
            Session::flush();
            return redirect('/account-suspended');
        }

        return $next($request);
    }
}
