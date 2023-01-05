<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Exports\CsvExport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CSVController extends Controller
{

    public $columns;
    public $heading;
    public $dateTime;
    public $userId;

    public function __construct(Request $request)
    {
        $this->columns = explode(',', $request->columns);
        $this->heading = explode(',', $request->headings);
        $this->userId = $request->user_id;
        $this->dateTime = Carbon::now('America/Los_Angeles')->format("F j, Y, g:i A");
    }
    public function downloadCSV(Request $request, string $type)
    {
        if ($type === 'users') {
            $userHeading = [];
            $userColumn = [];
            foreach($this->columns as $columns) {
                if ($columns === 'full_name') {
                    array_push($userHeading, 'First Name', 'Last Name');
                    array_push($userColumn, 'first_name', 'last_name');
                }
                if ($columns === 'email') {
                    array_push($userHeading, 'Email');
                    array_push($userColumn, 'email');
                }
                if ($columns === 'number') {
                    array_push($userHeading, 'Number');
                    array_push($userColumn, 'mobile_number');
                }
                if ($columns === 'location') {
                    array_push($userHeading, 'Location');
                    array_push($userColumn, DB::raw('CONCAT( p.state, " ", p.city, " ", p.country) location'));
                }
                if ($columns === 'status') {
                    array_push($userHeading, 'Status');
                    array_push($userColumn, 'status');
                }
                if ($columns === 'last_sign_in') {
                    array_push($userHeading, 'Last Sign In');
                    array_push($userColumn, 'last_login_at');
                }
            }
            return Excel::download(new CsvExport('user', $userColumn, $userHeading), 'users-'.$this->dateTime.'.csv');
        }

        if ($type === 'group' || $type === 'User-Groups') {
            $groupHeading = [];
            $groupColumn = [];
            foreach($this->columns as $columns) {
                
                if ($columns === 'description') {
                    array_push($groupHeading, 'Description');
                    array_push($groupColumn, 'groups.description');
                }
                if ($columns === 'group_owner') {
                    array_push($groupHeading, 'Group Owner');
                    array_push($groupColumn, DB::raw('CONCAT(users.first_name, " ", users.last_name) group_owner'));
                }
                if ($columns === 'total_members') {
                    array_push($groupHeading, 'Total Members');
                    array_push($groupColumn, 'groups.total_members');
                }
                if ($columns === 'status') {
                    array_push($groupHeading, 'Status');
                    array_push($groupColumn, 'groups.status');
                }
            }
            return Excel::download(new CsvExport($type, $groupColumn, $groupHeading, $this->userId), $type . '-'.$this->dateTime.'.csv');
        }

        if ($type === 'events' || $type === 'User-Events') {
            $eventHeading = [];
            $eventColumn = [];
            foreach($this->columns as $columns) {
                
                if ($columns === 'description') {
                    array_push($eventHeading, 'Description');
                    array_push($eventColumn, 'events.description');
                }
                if ($columns === 'event_owner') {
                    array_push($eventHeading, 'Event Owner');
                    array_push($eventColumn, DB::raw('CONCAT(users.first_name, " ", users.last_name) event_owner'));
                }
                if ($columns === 'total_members') {
                    array_push($eventHeading, 'Total Members');
                    array_push($eventColumn, DB::raw('(SELECT count(id) FROM user_events WHERE event_id = events.id) total_member'));
                }
                if ($columns === 'status') {
                    array_push($eventHeading, 'Status');
                    array_push($eventColumn, 'events.status');
                }
            }
            return Excel::download(new CsvExport($type, $eventColumn, $eventHeading, $this->userId), $type .'-'.$this->dateTime.'.csv');
        }
    }
}
