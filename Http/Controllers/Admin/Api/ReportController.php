<?php

namespace App\Http\Controllers\Admin\Api;

use Carbon\Carbon;
use App\Models\Note;
use App\Models\User;
use App\Models\Event;
use App\Models\Group;
use App\Enums\WallType;
use App\Models\Reports;
use App\Enums\TakeAction;
use App\Enums\UserStatus;
use App\Models\UserEvent;
use App\Models\UserGroup;
use App\Traits\AdminTraits;
use App\Enums\GatheringType;
use App\Enums\GeneralStatus;
use App\Mail\TakeActionMail;
use App\Traits\ApiResponser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Jobs\SendEmailTakeAction;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Repository\Reports\ReportRepository;
use App\Repository\Event\EventRepository;
use App\Scopes\AccountNotSuspendedScope;

class ReportController extends Controller
{
    use ApiResponser, AdminTraits;

    public function flagSuspend(Request $request)
    {
        try {
            $userId = $request->userId;
            $resourceId = $request->resource_id;
            $resource = $request->resource;
            $notes = $request->notes;
            $type = $request->type;
            $attachments = $request->attachments;
            $reporterId = authUser()->id;

            Note::create([
                'type' => $type,
                'reporter_id' => authUser()->id,
                'notable_type' => $resource,
                'notable_id' => $resourceId,
                'note' => $notes,
                'media_id' => json_encode($attachments)
            ]);

            if ($resource === 'user' && $type === TakeAction::REACTIVATE) {

                $this->deleteReport($userId, 'user');

                $user = User::withoutGlobalScopes()->where('id', $userId)
                    ->update([
                        'suspended_at' => NULL,
                        'status' => UserStatus::PUBLISHED
                    ]);

                // return $this->successResponse($user);
            }

            //if the resource is group or event it should be check if its already published or activated
            /* Will comment this block for future used
            if ($resource === 'group') {
                $isPublished = Group::whereId($resourceId)->whereNotNull('published_at')->exists();
                if (!$isPublished) {
                    return $this->errorResponse('Group can be ' . $type . ' because is not yet published', Response::HTTP_FORBIDDEN);
                }
            }

            if ($resource === 'event') {
                $isPublished = Event::whereId($resourceId)->where('is_published', 1)->exists();
                if (!$isPublished) {
                    return $this->errorResponse('Event can be ' . $type . ' because is not yet published', Response::HTTP_FORBIDDEN);
                }
            }
            */

            $result = ReportRepository::saveOrUpdateReport($notes, $userId, $reporterId, $type, $attachments, $resource, $resourceId);

            if ($result && $resource === 'user') {
                $getUser = User::withoutGlobalScope(AccountNotSuspendedScope::class)->withTrashed()->whereId($userId);
                $user = $getUser->first();

                if ($type === TakeAction::FLAG || $type === TakeAction::DEACTIVATE) {
                    $getUser->update([
                        'suspended_at' => NULL,
                        'status' => $type === TakeAction::FLAG ? UserStatus::FLAGGED : UserStatus::DEACTIVATED,
                    ]);
                }
                elseif ($type === TakeAction::SUSPEND) {
                    $getUser->update(['suspended_at' => Carbon::now(), 'status' => GeneralStatus::SUSPENDED]);
                }
                elseif ($type === TakeAction::DELETE) {
                    $getUser->update([
                        'status' => GeneralStatus::DELETED
                    ]);
                    $getUser->delete();
                }
                elseif ($type === TakeAction::UNSUSPEND) {
                    $getUser->update([
                        'suspended_at' => NULL,
                        'status' => UserStatus::PUBLISHED
                    ]);
                }
                elseif ($type === TakeAction::REACTIVATE || $type === TakeAction::REMOVE_FLAG) {
                    $getUser->update([
                        'status' => UserStatus::PUBLISHED
                    ]);
                }

                $details = [
                    'action' => $type,
                    'actionType' => strtolower(GeneralStatus::map()[$this->takeActionType($type) - 1]['value']),
                    'resource' => $resource,
                    'note' => $notes,
                    'from' => 'support@perfectfriends.com',
                    'subject' => 'Notification of Account ' . $this->reportType($type),
                    'owner' => $user->first_name,
                    'title' => 'account'
                ];

                if ($user->validTypeAccount) {
                    Mail::to($user->email)
                        ->send(new TakeActionMail($details));
                }
            }

            if ($result && $resource === 'group') {

                $group = Group::whereId($resourceId)->withTrashed();

                $user = User::withTrashed()->whereId($group->first()->user_id)->first();

                $listOfUsers = UserGroup::where('group_id', $resourceId)->with('user')->get()->pluck('user');

                if ($type === TakeAction::FLAG) {
                    $group->update(['status' => GeneralStatus::FLAGGED]);
                } else if ($type === TakeAction::SUSPEND) {
                    $group->update(['status' => GeneralStatus::SUSPENDED]);
                } else if ($type === TakeAction::DEACTIVATE) {
                    $group->update([
                        'published_at' => NULL,
                        'status' => GeneralStatus::DEACTIVATED
                    ]);
                } else if ($type === TakeAction::REACTIVATE) {
                    $group->update([
                        'published_at' => Carbon::now(),
                        'status' => GeneralStatus::PUBLISHED
                    ]);
                } else if ($type === TakeAction::REMOVE_FLAG || $type === TakeAction::UNSUSPEND) {
                    $group->update(['status' => GeneralStatus::PUBLISHED]);
                } else if ($type === TakeAction::DELETE) {
                    $group->update(['status' => GeneralStatus::DELETED]);
                    $group->delete();
                }

                $details = [
                    'action' => $type,
                    'actionType' => strtolower(GeneralStatus::map()[$this->takeActionType($type) - 1]['value']),
                    'resource' => $resource,
                    'note' => $notes,
                    'from' => 'support@perfectfriends.com',
                    'subject' => 'Notification of Group ' . $this->reportType($type),
                    'owner' => $user->first_name,
                    'title' => $group->first()->name
                ];

                // Checking for valide email will be done on the jobs
                SendEmailTakeAction::dispatch($listOfUsers, $details)->onQueue('high');
            }

            if ($result && (strtolower($resource) === strtolower(GatheringType::EVENT_STR) || $resource === strtolower(GatheringType::HUDDLE_STR))) {

                $event = Event::whereId($resourceId);
                $user = User::whereId($event->first()->user_id)->first();
                // Get event information before update
                $eventInfo = $event->withTrashed()->first();

                $listOfUsers = UserEvent::where('event_id', $resourceId)->with('user')->get()->pluck('user');

                if ($type === TakeAction::FLAG) {
                    $event->update(['status' => GeneralStatus::FLAGGED]);
                } else if ($type === TakeAction::SUSPEND) {
                    $event->update(['status' => GeneralStatus::SUSPENDED]);
                } else if ($type === TakeAction::DEACTIVATE) {
                    $event->update([
                        'is_published' => 0,
                        'status' => GeneralStatus::UNPUBLISHED
                    ]);
                } else if ($type === TakeAction::REACTIVATE) {
                    $event->update([
                        'is_published' => 1,
                        'status' => GeneralStatus::PUBLISHED
                    ]);
                } else if ($type === TakeAction::REMOVE_FLAG || $type === TakeAction::UNSUSPEND) {
                    $event->update(['status' => GeneralStatus::PUBLISHED, 'published_date' => date("Y-m-d")]);
                } else if ($type === TakeAction::DELETE) {
                    $event->update(['status' => GeneralStatus::DELETED]);
                    $event->delete();
                } else if ($type == TakeAction::PUBLISH) {
                    $event->update([
                        'published_date' => date("Y-m-d"),
                        'is_published' => 1,
                        'status' => GeneralStatus::PUBLISHED
                    ]);
                } else {
                    $event->update(['status' => $this->takeActionType($type)]);
                }

                $details = [
                    'action' => $type,
                    'actionType' => strtolower(GeneralStatus::map()[$this->takeActionType($type) - 1]['value']),
                    'resource' => $resource,
                    'note' => $notes,
                    'from' => 'support@perfectfriends.com',
                    'subject' => 'Notification of ' . ucfirst($resource) . ' ' . $this->reportType($type),
                    'owner' => $user->first_name,
                    'title' => $eventInfo->title
                ];

                // Checking for valide email will be done on the jobs
                SendEmailTakeAction::dispatch($listOfUsers, $details)->onQueue('high');
            }

            return $this->successResponse($result, '', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->errorResponse('Something went wrong ' . $e->getMessage(), Response::HTTP_OK);
        }
    }

    public function deleteReport($userId, $type)
    {
        $report = Reports::where('user_id', $userId)
            ->where('resource', $type);

        if ($report->exists()) {
            $report->delete();
        }

        return $report;
    }
}
