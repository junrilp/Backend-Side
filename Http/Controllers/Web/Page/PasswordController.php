<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PasswordController extends Controller
{
    public function creation(Request $request)
    {
        return Inertia::render('PasswordCreation', [
            'validationToken' => $request->validationToken,
            'title' => '- Set password'
        ]);
    }

    public function changeCredentialMobile(Request $request)
    {
        return Inertia::render('ChangeCredentialMobile', [
            'page'            => 'change-credential-mobile',
            'validationToken' => $request->validationToken,
            'appMobileUrl' => env('APP_MOBILE_URL'),
            'title' => 'Change credentials'
        ]);
    }
}
