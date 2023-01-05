<?php

namespace App\Http\Controllers\Web\Page;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SmsVerificationController extends Controller
{
    public function index(){
        return Inertia::render('Sms/SmsVerification', [
            'title' => '- SMS Verification | Perfect Friend'
        ]);
    }

}
