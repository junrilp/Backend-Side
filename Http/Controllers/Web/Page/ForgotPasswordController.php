<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ForgotPasswordController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function index()
    {
        return Inertia::render('ForgotPassword', ['title' => '- Forgot password']);
    }
}
