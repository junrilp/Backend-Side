<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;

class HandleEmailVerificationRequests
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
        $token = $request->token;
        $isValidToken = User::where('validate_token', $token)
                            ->first();

        if ($isValidToken->status === 2) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 2,
                    'message' => 'Email already verified. Please Login'
                ], 200);
            } else {
                abort(409);
            }
        } else {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 2,
                    'message' => 'Token not found.'
                ], 404);
            } else {
                abort(404);
            }
        }

        return $next($request);
    }
}
