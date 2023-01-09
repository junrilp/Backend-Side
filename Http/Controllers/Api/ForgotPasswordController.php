<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordChangeRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\User;
use App\Mail\ForgotPassword;
use App\Models\PasswordReset;
use App\Traits\ApiResponser;
use Mail;
use Hash;

class ForgotPasswordController extends Controller
{

    use ApiResponser;
    /**
     * Request for password change and verify user existence
     * After verification, will send an email for reset-password link
     *
     * @param  PasswordChangeRequest $request
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function passwordChange(PasswordChangeRequest $request)
    {
        $user = User::where(strtolower('email'), strtolower($request->email))->first();
        if ($user) {

            /** @author Junril Pateño <junril.p@ragingriverict.com> */
            if ($user->status == UserStatus::NOT_VERIFIED || $user->password == null) {
                return $this->errorResponse("Sorry, You can't reset your password. Please check your email to activate your account.", Response::HTTP_NOT_FOUND);
            }
            /** END */

            Mail::to($user->email)->send(new ForgotPassword($user));
            // check for failed sending
            if (!Mail::failures()) {
                return $this->successResponse(null);
            }
        }
        return $this->errorResponse('Email Address was not found.', Response::HTTP_NOT_FOUND);
    }

    /**
     * Reset user password
     * For verification check hash if changed
     *
     *
     * @param  ResetPasswordRequest $request
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function resetPassword(ResetPasswordRequest $request)
    {
        try {
            $helper     = encrypter(); // will use for 2nd layer of encryption
            $email      = strtolower($helper->decrypt(decrypt($request->email)));
            $token      = $helper->decrypt($request->token);
            $password   = $request->password;
            $queryReset = PasswordReset::query();
            $reset      = (clone $queryReset)->where(strtolower('email'), $email)->first(); // use clone to not modify the query so we can reuse it on deletion of reset token
            // check if hash match
            if (!Hash::check($token, $reset->token)) {
                return $this->errorResponse('Unauthorized Access.', Response::HTTP_UNAUTHORIZED);
            }

            if (User::where(strtolower('email'), $email)->update(['password' => bcrypt($password)])) {
                (clone $queryReset)->where(strtolower('email'), $email)->delete();
            }
            return $this->successResponse(null);
        } catch (\Exception $e) {
            abort(404);
        }
    }
}
