<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Event;
use App\Models\Timezone;
use App\Enums\TimeZone as EventTimeZone;
use Carbon\Carbon;
use Gate;

class EventDateTimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return Gate::allows('can_edit_event', $this->event) ||  $this->event->user_id === authUser()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'event_start'       => ['bail', 'required_if:timezone_id,>,0', 'date', function ($attribute, $value, $fail) {
                try {
                    $event = Event::findOrFail($this->event->id);
                    $event->timezone = Timezone::find($this->post('timezone_id'));

                    if ($event->has_started) {

                        if ($event->event_start !== $value) {
                            $fail('Event has already started and you can\'t modify the start date anymore.');
                        }

                    } else {
                        $startTimeOfEventTz = (new Carbon($value.' '.$this->post('start_time')))
                            ->shiftTimezone( $event->timezone ? $event->timezone->name : EventTimeZone::CENTRAL);

                        if ($startTimeOfEventTz
                        ->isPast()) {
                            $fail('Start date & time should not be a past.');
                        }
                    }
                } catch (\Exception $e) {
                    $fail('Cannot verify start date & time.');
                }
            }],
            'start_time'        => ['bail', 'required'],
            'event_end'         => ['bail', 'required', 'date', 'after_or_equal:event_start'],
            'end_time'          => ['bail', 'required', function ($attribute, $value, $fail) {
                $eventStart = $this->post('event_start');
                $startTime = $this->post('start_time');
                $eventEnd = $this->post('event_end');
                $endTime = $this->post('end_time');

                if (!($eventStart && $startTime && $eventEnd && $endTime)) {
                    return;
                }

                if (Carbon::parse("{$eventStart} {$startTime}")->greaterThanOrEqualTo(Carbon::parse("{$eventEnd} {$endTime}"))) {
                    $fail('End time must be after start time.');
                }
            }],
            'timezone_id'       => 'required'
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'event_start.required'      => 'Event start date is required.',
            'start_time.required'       => 'Event time start is required.',
            'event_end.required'        => 'Event end date is required.',
            'end_time.required'         => 'Event end time is required.',
        ];
    }
}
