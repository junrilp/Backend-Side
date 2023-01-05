<?php

namespace App\Http;

use App\Http\Middleware\AccountNotVerified;
use App\Http\Middleware\AccountVerified;
use App\Http\Middleware\AccountPublished;
use App\Http\Middleware\EventAuthorizedUser;
use App\Http\Middleware\GroupAuthorizedUser;
use App\Http\Middleware\PremiumSubscription;
use App\Http\Middleware\ProfileBuilder;
use App\Http\Middleware\IsAccountSuspended;
use App\Http\Middleware\PinCodeVerified;
use App\Http\Middleware\EventBuilder;
use App\Http\Middleware\HandleGroupEdit;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\TrustProxies::class,
        \Fruitcake\Cors\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\ForceJsonResponse::class,
        \App\Http\Middleware\Cors::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Laravel\Passport\Http\Middleware\CreateFreshApiToken::class,
            \App\Http\Middleware\ValidateToken::class,
        ],

        'api' => [
            'throttle:200,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'json.response' => \App\Http\Middleware\ForceJsonResponse::class,
        'cors' => \App\Http\Middleware\Cors::class,
        'handle-email-verification-requests' => \App\Http\Middleware\HandleEmailVerificationRequests::class,
        'conceal-message-content' => \App\Http\Middleware\ConcealMessageContents::class,
        'validate-username-password' => \App\Http\Middleware\ValidatedUserNamePassword::class,
        'profile-builder' => ProfileBuilder::class,
        'account-not-verified' => AccountNotVerified::class,
        'account-verified' => AccountVerified::class,
        'account-published' => AccountPublished::class,
        'premium-subscription' => PremiumSubscription::class,
        'event-authorized' => EventAuthorizedUser::class,
        'is-admin' => \App\Http\Middleware\IsAdmin::class,
        'group-authorized' => GroupAuthorizedUser::class,
        'is-account-suspended' => IsAccountSuspended::class,
        'pin-code-verified' => PinCodeVerified::class,
        'event-builder' => EventBuilder::class,
        'handle-group-edit' => HandleGroupEdit::class,
    ];
}
