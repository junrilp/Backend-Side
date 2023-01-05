<?php

namespace App\Http\Requests;

use Gate;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class EventUnpublishRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {

        return Gate::allows('can_edit_event', $this->event) || ($this->event && $this->event->user_id === auth()->id());

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'status'                => ['required'],
            'new_event_start'       => ['bail', 'required_if:status,rescheduled', 'date', 'after:today'],
            'new_start_time'        => ['bail', 'required_if:status,rescheduled'],
            'new_event_end'         => ['bail', 'required_if:status,rescheduled', 'date', 'after_or_equal:event_start'],
            'new_end_time'          => ['bail', 'required_if:status,rescheduled', function($attribute, $value, $fail) {

                $eventStart = $this->post('new_event_start');
                $startTime  = $this->post('new_start_time');
                $eventEnd   = $this->post('new_event_end');
                $endTime    = $this->post('new_end_time');

                if(!($eventStart && $startTime && $eventEnd && $endTime)) {
                    return;
                }

                if (Carbon::parse("{$eventStart} {$startTime}")->greaterThanOrEqualTo(Carbon::parse("{$eventEnd} {$endTime}"))) {
                    $fail('End time must be after start time.');
                }
            }],
            'new_time_zone'         => ['bail', 'required_if:status,rescheduled'],
        ];
    }
}
