<?php

namespace App\Http\Requests;

use Gate;
use Illuminate\Foundation\Http\FormRequest;
use App\Rules\MaxCapacity;
use App\Enums\GatheringType;

class EventNameLocationRequest extends FormRequest
{
    protected $stopOnFirstFailure = true;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        switch ($this->method()) {
            case 'POST':
                return true;
                break;
            case 'PATCH':
                // If method is patch, check if the request came from the owner of the event
                return Gate::allows('can_edit_event', $this->event) ||  $this->event->user_id === authUser()->id;
                break;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title'                 => ['required', 'max:191', function ($attribute, $value, $fail) {
                                            $restricted = checkWordRestriction($this->post('title'));
                                            if ($restricted) {
                                                $fail($restricted);
                                            }
                                        }],
            'setting'               => ['required', 'integer'],
            'venue.venue_location'  => ['required_if:setting,1'],
            'venue.city'            => ['required_if:setting,1'],
            'venue.latitude'        => ['required_if:setting,1'],
            'venue.longitude'       => ['required_if:setting,1'],
            'rsvp_type'             => ['required_if:gathering_type,'.GatheringType::EVENT],
            'max_capacity'          => ['required_if:rsvp_type,2',new MaxCapacity]
        ];
    }

    /**
     * Get the validation messages that apply to the rules.
     * @return array
     */
    public function messages()
    {
        return [
            'title.required'             => 'Event title is required.',
            'setting.required'           => 'Setting is required.',
            'setting.numeric'            => 'Setting should be integer.',
            'venue.venue_location.required_if' => 'Venue location is required',
            'venue.city.required_if'           => 'City is required',
            'venue.latitude.required_if'       => 'Latitude is required',
            'venue.longitude.required_if'      => 'Longitude is required',
            'rsvp_type.required'        => 'RSVP Type is required.',
            'max_capacity.required_if'  => 'Max Capacity is required.'
        ];
    }
}
