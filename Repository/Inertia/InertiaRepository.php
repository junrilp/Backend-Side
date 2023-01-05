<?php

namespace App\Repository\Inertia;

use App\Models\User;
use App\Http\Resources\AuthUserResource;
use Illuminate\Support\Facades\Auth;

class InertiaRepository implements InertiaInterface
{
    public static function getSharedData()
    {
        $messagingSharedData = [];
        $eventSharedData = [];

        $authDetails = null;
        if (Auth::check()) {
            $user = Auth::user();
            $authDetails = User::where('id', '=', Auth::id())
                ->with('primaryPhoto')
                ->select(['id', 'image', 'user_name', 'first_name', 'last_name', 'account_type', 'status'])
                ->first();

            $messagingSharedData = [
                'can_access_messaging' => $user->canAccessMessaging(),
            ];

            if (!empty($user->profile)) {
                $eventSharedData = [
                    'userProfile' => [
                        'latitude' => $user->profile->latitude,
                        'longitude' => $user->profile->longitude,
                        'city' => $user->profile->city,
                        'state' => $user->profile->state
                    ],
                ];
            }
        }

        return [
            'auth' => $authDetails !== null ? new AuthUserResource($authDetails) : null,
            'messaging' => $messagingSharedData,
            'events' => $eventSharedData,
            'previousUrl' => url()->previous(),
            'greetBday' => request()->session()->get('greetBday'),
            'flash' => function () {
                return request()->session()->get('flash');
            },
        ];
    }
}
