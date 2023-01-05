<?php

namespace App\Http\Middleware;

use App\Enums\UserSubscriptionType;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class PremiumSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (auth()->guest()) {
            return redirect()->route('homepage');
        } else if (auth()->check() && auth()->user()->subscription_type === UserSubscriptionType::PREMIUM) {
            $redirectTo = $request->get('redirect');
            if ($request->url() === $redirectTo || url()->previous() === $request->url()) {
                return redirect()->route('homepage');
            } elseif ($redirectTo) {
                return Redirect::to($redirectTo);
            } else {
                return redirect()->back();
            }
        }

        return $next($request);
    }
}
