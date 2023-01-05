<?php

namespace App\Http\Middleware;

use App\Forms\PerfectFriendForm;
use App\Models\UserPreference;
use App\Models\UserProfile;
use Closure;
use Illuminate\Http\Request;

class ProfileBuilder
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $step)
    {
        if($step === 'step-1' AND !PerfectFriendForm::validateStep1(auth()->id())){
            return redirect('profile-builder/step-1');
        }

        if($step === 'step-2' AND !PerfectFriendForm::validateStep2(auth()->id())){
            return redirect('profile-builder/step-2');
        }

        if ($step === 'step-3' AND !PerfectFriendForm::validateStep3(auth()->id())){
            return redirect('profile-builder/step-3');
        }

        if ($step === 'step-5' AND !PerfectFriendForm::validateStep5(auth()->id())) {
            return redirect('profile-builder/step-5');
        }

        return $next($request);

    }
}
