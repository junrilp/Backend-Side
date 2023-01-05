<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmsPhoneVerification;
use App\Http\Requests\SMSPinVerification;
use App\Repository\Sms\SmsRepository;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmsVerificationController extends Controller
{

    use ApiResponser;
    private $smsRepository;

    public function __construct(SmsRepository $smsRepository)
    {
        $this->smsRepository = $smsRepository;
    }

    /**
     * Send an SMS PIN Code verification to user
     *
     * @param SmsPhoneVerification
     * @author Angelito Tan
     */
    public function sendPhoneSMSVerification(SmsPhoneVerification $request)
    {
        $mobileNumber = Str::replace('-', '', $request->mobile_number);
        $ipAddress = $request->ip();
        $this->smsRepository->sendPhoneSMSVerification($mobileNumber, $ipAddress);
        return $this->successResponse(null);
    }

    /**
     * Verify PIN code from user
     *
     * @param Request
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     * @author Angelito Tan
     */
    public function verifyPinCode(SMSPinVerification $request)
    {
        $mobileNumber = Str::replace('-', '', $request->mobile_number);
        $pin = $request->pin;
        $this->smsRepository->verifyPinCode($mobileNumber, $pin);
        return $this->successResponse(null);
    }
}
