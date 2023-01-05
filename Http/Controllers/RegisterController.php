<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function index()
    {
        return Inertia::render('Register', [
            'temporaryUsername' => 'guest-123'
        ]);
    }
}
