<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class GroupAuthorizedUser
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
        $request['hideTitle'] = true;

        if (!auth()->check()) {
            throw new Exception('Plese login first');
            return;
        }

        if (!$request->group || $request->group->user_id !== auth()->id()) {
            //set code to 200 so it wont give an error 500
            //throw new Exception('You don\'t have enough permissions to view this page. Only the group owner can view this page.', 200);

            return Inertia::render('Error', [
                'status'    => 403,
                'message'   => 'Only the group owner can view the preview page.',
                'hideStatus' => true,
                'title' => 'You don\'t have enough permissions to view this page'
            ]);


        }

        $request['hideTitle'] = false;
        return $next($request);
    }
}
