<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Group;
use App\Traits\ApiResponser;

class HandleGroupEdit
{
    use ApiResponser;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if(!$request->is('api/admin-panel/*')) {
            if (!$request->route('group')->can_edit) {
                if ($request->expectsJson() && $request->is('api/*')) {
                    return $this->errorResponse('Sorry, you don\'t have permission to edit this group.', Response::HTTP_FORBIDDEN);
                } else {
                    abort(403);
                }
            }
        }

        return $next($request);
    }
}
