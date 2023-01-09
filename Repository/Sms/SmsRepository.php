<?php
namespace App\Repository\Sms;

use App\Enums\SmsStatusType;
use App\Models\SmsVerification;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;
use App\Models\User;
use App\Enums\UserStatus;
use DB;

class SmsRepository implements SmsInterface
{

    private $mobileNumber;
    private $ipAddress;
    private $sms;
    private $maxAttempt = 5;
    private $localCode = 1; //default

    /**
     * Send an SMS PIN Code verification to user
     *
     * @param String $mobileNumber
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function sendPhoneSMSVerification(String $mobileNumber, String $ipAddress)
    {
        $this->mobileNumber = Str::replace('-', '', $mobileNumber);
        $this->ipAddress = $ipAddress;

        $this->generatePinCode()
            ->findOrCreateSMSRecord()
            ->sendSMSPinCode()
            ->updateSMSRecord();
    }

    /**
     * Generate a PIN code, will be use to send to the user
     * For verification of the code in the database
     *
     * @return $this
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function generatePinCode()
    {
        $numberLists = Arr::shuffle(range(0, 9)); // shuffle the list of array
        $randomPin = Arr::random($numberLists, 6); // selected 6 random number
        $this->generatedPin = implode('', $randomPin);
        return $this;
    }

    /**
     * Will check if there's already a record if not it will insert a new record
     *
     * @return $this
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function findOrCreateSMSRecord()
    {
        // Check if phone already stored
        // Will use the return eloquent data if phone already exist
        $this->sms = SmsVerification::firstOrCreate([
            'mobile_number' => $this->mobileNumber,
        ], [
            'ip_address' => $this->ipAddress
        ]);

        if (
            $this->sms->user && $this->sms->user->status !== UserStatus::NOT_VERIFIED
        ) {
            throw new \Exception('This phone number is already registered.', Response::HTTP_CONFLICT);
        }

        $this->sms->attempt = ($this->sms->attempt + 1);
        $minutesPassed = Carbon::now()->diffInMinutes($this->sms->updated_at); // always get the update_at

        // Check if max attempt is reached
        if ($this->sms->attempt > $this->maxAttempt && $minutesPassed < 1){
            throw new \Exception("You have {$this->maxAttempt} attempts with this phone number, <br/>Please wait 1 minute to try again. <br/>Contact us for immediate assistance.", Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // reset the attempt into 1
        if ($this->sms->attempt > $this->maxAttempt && $minutesPassed >= 1){
            $this->sms->attempt = 1;
        }

        $this->sms->save();

        // If phone is already confirmed and registered throw an error
        $alreadyUsed = User::where(DB::raw('REPLACE(mobile_number, "-", "")'), $this->mobileNumber)
            ->where('status', '!=', UserStatus::NOT_VERIFIED)
            ->exists();
        if (
            $this->sms->status === SmsStatusType::CONFIRMED_AND_REGISTERED && 
            $alreadyUsed
        ) {
            throw new \Exception('This mobile number is already in use, please contact support.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        return $this;
    }

    /**
     * Send an SMS Pin code
     *
     * @return $this
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function sendSMSPinCode()
    {
        try {
            $client = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            $message = "Your PIN code is: $this->generatedPin";
            $client
                ->messages
                ->create(
                    "+{$this->localCode}{$this->mobileNumber}",
                    [
                        'from' => env('TWILIO_PHONE_NUMBER'),
                        'body' => $message,
                    ]
                );
        } catch (TwilioException $e) {
            \Log::alert($e->getMessage());
            throw new TwilioException($e->getMessage());
        }
        return $this;
    }

    /**
     * Update the SMS Record
     * It will update pin and status of the pin code
     *
     * @return $this
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function updateSMSRecord()
    {
        $this->sms->update([
            'pin' => $this->generatedPin,
            'status' => SmsStatusType::NOT_CONFIRMED,
        ]);
        return $this;
    }

    /**
     * Verify PIN code from user
     *
     * @param String $mobileNumber
     * @param String $pinCode
     * @return mixed
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function verifyPinCode(String $mobileNumber, String $pinCode)
    {
        $this->mobileNumber = Str::replace('-', '', $mobileNumber);
        $verifyPin = SmsVerification::where('mobile_number', $this->mobileNumber)
            ->where('pin', $pinCode)
            ->where('status', '!=', SmsStatusType::CONFIRMED_AND_REGISTERED)
            ->first();

        if ($verifyPin) {
            // Expiration of 5 minutes, check if not expired
            $minutesPassed = Carbon::now()->diffInMinutes($verifyPin->updated_at); // always get the update_at incase user re-send a code
            if ($minutesPassed > 5) {
                throw new \Exception('PIN Code is already expired, re-send a new one.', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $verifyPin->update(['status' => SmsStatusType::CONFIRMED]);
            return true;
        }
        throw new \Exception('PIN verification code is invalid', Response::HTTP_NOT_FOUND);
    }

    /**
     * Send a message text
     *
     * @return $this
     * @author Junril Pateño <junril090693@gmail.com>
     */
    public function sendSMSText(string $mobileNumber, string $message)
    {
        try {
            $client = new Client(env('TWILIO_SID'), env('TWILIO_AUTH_TOKEN'));
            $client
                ->messages
                ->create(
                    "+{$this->localCode}{$mobileNumber}",
                    [
                        'from' => env('TWILIO_PHONE_NUMBER'),
                        'body' => $message,
                    ]
                );
        } catch (TwilioException $e) {
            \Log::alert($e->getMessage());
        }
        return $this;
    }
}
