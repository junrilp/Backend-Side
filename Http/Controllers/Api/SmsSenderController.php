<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\SmsSendMessageRequest;
use App\Http\Controllers\Controller;
use App\Jobs\SmsSendToUsersJob;
use App\Models\SmsTextMessage;
use App\Enums\SmsType;
use App\Enums\UserStatus;
use App\Models\UserEvent;
use App\Models\UserGroup;
use App\Models\User;
use App\Enums\TableLookUp;
use App\Traits\ApiResponser;
use App\Http\Resources\SmsSentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Enums\IsCaseType;

class SmsSenderController extends Controller
{
    use ApiResponser;

    public function sendSMS(SmsSendMessageRequest $request){
        $tableLookUp = $request->table_lookup;
        $tableId = $request->table_id;
        $smsType = $request->sms_type;
        $message = $request->message;
        $userId = authUser()->id;
        $userLists = [];

        if ($tableLookUp === TableLookUp::EVENTS) {

            $eventQuery = UserEvent::where('event_id', $tableId)
                ->whereHas('user', function($query) use ($userId) {
                    $query->whereNotNull('mobile_number')
                        ->where('status', UserStatus::PUBLISHED)
                        ->whereIn('is_case', [IsCaseType::NORMAL_ACCOUNT, IsCaseType::TEST_ACCOUNT])
                        ->where('user_id', '!=', $userId);
                });

            $userLists = (clone $eventQuery)
                ->when($smsType === SmsType::ATTENDING, function($query) {
                    // list of user for attending
                    return $query->whereNotNull('qr_code')
                                ->where('owner_flagged', 0);
                })
                ->when($smsType === SmsType::CHECKED_IN, function($query) {
                    // list of user for checked-in
                    return $query->whereNotNull('attended_at')
                                ->where('owner_flagged', 0);
                })
                ->when($smsType === SmsType::WAIT_LISTED, function($query) {
                    // list of user for wait listed
                    return $query->whereNull('qr_code')
                            ->where('owner_flagged', 0);
                })
                ->when($smsType === SmsType::INVITED, function($query) {
                    // list of user for invited
                    return $query
                            ->whereNotNull('qr_code')
                            ->where('owner_flagged', 0);
                })
                ->when($smsType === SmsType::AWAITING_INVITE, function($query) {
                    // list of user for awaiting invite or not invited
                    return $query->where(function($query){
                        $query->whereNull('qr_code')
                            ->where('owner_flagged', 0);
                    });
                })
                ->when($smsType === SmsType::FLAGGED, function($query) {
                    // list of user for flagged
                    return $query->where('owner_flagged', 1);
                })
                ->get();
        }

        if ($tableLookUp === TableLookUp::PERSONAL_MESSAGE) {
            $userLists = User::where('id', '!=', $userId)
                            ->whereNotNull('mobile_number')
                            ->where('status', UserStatus::PUBLISHED)
                            ->whereIn('is_case', [IsCaseType::NORMAL_ACCOUNT, IsCaseType::TEST_ACCOUNT])
                            ->get();
        }

        if ($tableLookUp === TableLookUp::GROUPS) {
            $userLists = UserGroup::where('group_id', $tableId)
                ->whereHas('user', function($query) use ($userId) {
                    $query->whereNotNull('mobile_number')
                        ->where('status', UserStatus::PUBLISHED)
                        ->whereIn('is_case', [IsCaseType::NORMAL_ACCOUNT, IsCaseType::TEST_ACCOUNT])
                        ->where('user_id', '!=', $userId);
                })
                ->get();
        }

        $smsData = SmsTextMessage::create([
            'user_id' => $userId,
            'message' => $message,
            'table_id' => $tableId,
            'table_lookup' => $tableLookUp,
            'sms_type' => $smsType,
        ]);

        SmsSendToUsersJob::dispatch(['user_list' => $userLists, 'message' => $message])->onQueue('high');

       return $this->successResponse(new SmsSentResource($smsData));
    }

    public function getSentSMS(Request $request) {
        $perPage = $request->perPage ?? 5;
        $tableLookUp = $request->table_lookup;
        $tableId = $request->table_id;
        $smsType = $request->sms_type;
        $userId = authUser()->id;

        $smsData = SmsTextMessage::where('user_id', $userId)
                        ->where('table_id', $tableId)
                        ->where('sms_type', $smsType)
                        ->where('table_lookup', $tableLookUp)
                        ->latest()
                        ->paginate($perPage);

        return $this->successResponse(
            SmsSentResource::collection($smsData),
            'Success',
            Response::HTTP_OK,
            true
        );
    }
}
