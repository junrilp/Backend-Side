<?php

namespace App\Http\Controllers;

use App\Models\EmailDeliveryStatus;
use App\Models\User;
use Illuminate\Http\Request;

class AwsWebhookController extends Controller
{
    public function snsBouncedEmail(Request $request)
    {

        $output = json_decode(file_get_contents("php://input"), true);

        // This is an attempt at auto-confirming this subscription.
        if (isset($output['SubscribeURL'])) {
            // Do a basic curl to confirm the subscription if available. Otherwise, dump output to the database
            if (function_exists('curl_version')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $output['SubscribeURL']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $curlOutput = curl_exec($ch);
                curl_close($ch);
                // Exit after done. We can't handle the test payload any further
                exit();
            } else {

                // Can't continue anyway if the subscription isn't confirmed
                exit();
            }
        }

        $emailPart = str_replace('"', "", explode(",", explode(":", $output['Message'])[7]));

        EmailDeliveryStatus::updateOrCreate(
            ['email' =>  $emailPart[0]],
            ['status' => 'bounced']
        );

        User::where('email', $emailPart[0])->update(['is_case' => 1]);

    }
}
