<?php

namespace App\Http\Controllers\Web\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\PasswordReset;
use Jenssegers\Agent\Agent;
use Hash;
use Auth;
use Session;

/**
 * Reset password controller
 * This will validation the data and token hash
 *
 * @author Angelito Tan <angelito.t@ragingriverict.com>
 */
class ResetPasswordController extends Controller
{
    private $token;
    private $reset;
    /**
     * Validated email & token
     *
     * @return \Illuminate\Http\Response
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function index($encryptEmail, $token)
    {
        try{
            $agent       = new Agent();
            $helper      = encrypter(); // added layer for security reason.
            $email       = $helper->decrypt(decrypt($encryptEmail));
            $this->token = $helper->decrypt($token);
            $this->reset = PasswordReset::where('email', $email)->first();

            $this->isNullTokenAndReset()
                ->verifyTokenHash()
                ->logoutUser();

        } catch (\Exception $e){
            abort(404);
        }

        // Check if user came from mobile and has not access the page
        // Will check if the user mobile has already installed app
        if (!Session::exists('access_mobile_reset') && $agent->isMobile()) {
            Session::put('access_mobile_reset', true);
            return Inertia::render('ChangeCredentialMobile',[
                'page'  => 'reset-password',
                'email' => $encryptEmail,
                'validationToken' => $token,
                'appMobileUrl' => env('APP_MOBILE_URL')
            ]);
        }

        Session::forget('access_mobile_reset'); // remove this session
        return Inertia::render('ResetPassword',[
            'token' => $token,
            'email' => $encryptEmail,
            'title' => '- Reset password'
        ]);
    }

    /**
     * Check if token and reset data is null
     *
     * @return \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function isNullTokenAndReset(){
        if (is_null($this->token) || is_null($this->reset)){
            abort(404);
        }
        return $this;
    }

    /**
     * Verify the user token if changed
     *
     * @return \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function verifyTokenHash(){
        if (!Hash::check($this->token, $this->reset->token)) {
            abort(401);
        }
        return $this;
    }

    /**
     * Logout an existing user, for reset password landing page to be displayed
     * Logged-in user data will be removed
     *
     * @return $this
     * @author Angelito Tan <angelito.t@ragingriverict.com>
     */
    public function logoutUser(){
        if (Auth::check()) {
            Inertia::share('auth',null); // remove inertia shared page data
            Auth::logout(); // logout Auth
            Session::flush(); // remove session
        }
    }
}
