<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\RoleUser;

class EventAuthorizedUser
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
        $roleUserEventArray = RoleUser::roleUserEvents(authUser()->id)->adminOnly()->pluck('user_id')->toArray();

        if (
            auth()->check() &&
            $request->event && ($request->event->user_id === auth()->id() || in_array(authUser()->id, $roleUserEventArray))
        ) {
            return $next($request);
        }

        abort(403);
    }
}
