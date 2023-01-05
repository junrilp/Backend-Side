<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;

class LoginController extends Controller
{
    public function index(Request $request)
    {
        if (!empty($request->greet)) {
            $user = User::select('id')
                ->where('user_name', $request->greet)
                ->first();
            if ($user) {
                $request->session()->flash('greetBday', $user->id);
            }
        }

        return Inertia::render('Login', ['title' => '- Login']);
    }
}
