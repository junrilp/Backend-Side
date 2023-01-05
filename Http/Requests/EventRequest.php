<?php

namespace App\Http\Requests;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\MaxCapacity;

class EventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * @return array
     * * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function rules()
    {
        return [
            'title'             => ['bail', 'required', 'max:191', function ($attribute, $value, $fail) {
                                    $restricted = checkWordRestriction($this->post('title'));
                                    if ($restricted) {
                                        $fail($restricted);
                                    }
                                }],
            'image'             => ['bail', 'required', 'exists:media,id'],
            'rsvp_type'         => ['required'],
            'max_capacity'      => ['required_if:rsvp_type,2', new MaxCapacity],
            'description'       => ['bail', 'required', 'max:1000', function ($attribute, $value, $fail) {
                                    $restricted = checkWordRestriction($this->post('description'));
                                    if ($restricted) {
                                        $fail($restricted);
                                    }
                                }],
            'event_start'       => ['bail', 'required', 'date', 'after:today'],
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
            'timezone'         => ['bail', 'required'],
            'roles.*.user_id' => 'required',
            'roles.*.role_id' => 'required',
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'title.required'            => 'Event title is required.',
            'image.required'            => 'Event photo is required.',
            'description.required'      => 'About this event is required.',
            'event_start.required'      => 'Event start is required.',
            'start_time.required'       => 'Event time start is required.',
            'event_end.required'        => 'Event end is required.',
            'end_time.required'         => 'Event end time is required.',
            'max_capacity.required_if'  => 'Max Capacity is required.',
            'roles.*.user_id.required' => 'User is required',
            'roles.*.role_id.required' => 'Role is required',
        ];
    }
}
