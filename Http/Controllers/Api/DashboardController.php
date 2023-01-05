<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->bearerToken();
        $id = (new Parser(new JoseEncoder()))->parse($user)->claims()->all()['jti'];
        $auth = DB::table('oauth_access_tokens')
                    ->where('id', $id)
                    ->first();
        return response(['user'=>$auth, 'data'=>Auth::user()], 200);
        // return Inertia::render('Dashboard');
    }
}
