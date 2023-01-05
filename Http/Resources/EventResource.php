<?php

namespace App\Http\Resources;

use App\Enums\GeneralStatus;
use App\Enums\RSVPType;
use App\Forms\EnumForms;
use App\Forms\MediaForm;
use App\Models\Event;
use App\Models\RoleUser;
use App\Models\UserEvent;
use App\Http\Resources\Media as MediaResource2;
use App\Models\Note;
use App\Models\User;
use App\Repository\QrCode\QrCodeRepository;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     * * @author Junril Pateño <junril.p@ragingriverict.com>
     */
    public function toArray($request)
    {
        $event = new Event;

        $userRole = null;

        $eventInterests = [];

        if ($this->interests->all() != []) {
            $eventInterests = new InterestsResource($this->interests);
        }

        if (authCheck()) {
            $userEvent = $this->userEvents()
                ->where('user_id', authUser()->id)
                ->first();

            $roleUser = RoleUser::eventUserRole(authUser()->id, $this->id)->first();

            $userRole = $roleUser->role->label ?? null;
        }

        $admins   = $this->roleUser;
        $adminIds = $this->eventAdmins->pluck('user_id');

        if (isset($userEvent) && $userEvent) {
            $isVip = (bool) $userEvent->is_vip;
            $isGatekeeper = (bool) $userEvent->is_gatekeeper;
            $ticketDetails = [
                'user' => new UserBasicInfoResource2($userEvent->user),
                'qrCode' => $userEvent->qr_code != null ? QrCodeRepository::getQrCodeUrl($userEvent->qr_code) : null,
            ];
            $attendedAt = $userEvent->attended_at;
        }

        $confirmedAttendeesWoutOwner = UserEvent::where('event_id', $this->id)
            ->whereNotNull('qr_code')
            ->whereNotIn('user_id', $adminIds->merge($this->user_id)) // Exclude owner and admins
            ->count();

        $attendeesCount =  $this->attendees()
            ->where('user_id', '!=', $this->user_id) // Exclude owner
            ->whereNotIn('user_id', $adminIds) // Exclude admins
            ->count();

        return [
            "id" => $this->id,
            "past_type" => 'event',
            "owner" => new UserBasicInfoResource2(User::withoutGlobalScopes()->withTrashed()->findOrFail($this->user_id)),
            "abilities" => $this->abilities,
            "roles" => RoleUserResource::collection($admins),
            "admins" => RoleUserResource::collection($this->eventAdmins),
            "title" => $this->title,
            "slug" => $this->slug,
            "image_id" => $this->image,
            "video" => new MediaResource2($this->video),
            "image_url" => MediaForm::getImageURLById($this->image),
            "image_modified_url" => new Media($this->media),
            "description" => $this->description,
            "type" => $this->eventType ? new TypeResource($this->eventType) : null,
            "category" => $this->eventCategory ? new CategoryResource($this->eventCategory) : null,
            "rsvp_type" => $this->rsvp_type,
            "max_capacity" => $this->max_capacity,
            $this->mergeWhen($this->rsvp_type == RSVPType::LIMITED, [
                "capacity" => $this->when(
                    $this->resource->relationLoaded('attendees'),
                    $attendeesCount >= $this->max_capacity ? 'full' : 'open'
                ),
            ]),
            "setting" => $this->setting,
            "venue_type" => $this->show_at,
            "venue_location" => $this->venue_location,
            "secondary_location" => $this->secondary_location,
            "event_start" => $this->event_start,
            "start_time" => $this->start_time,
            "event_end" => $this->event_end,
            "end_time" => $this->end_time,
            "timezone" => EnumForms::getEnum((int) $this->timezone_id, 'App\Enums\TimeZone'),
            "street_address" => $this->street_address,
            "city" => $this->city,
            "state" => $this->state,
            "zip_code" => $this->zip_code,
            "country" => $this->country,
            "latitude" => $this->latitude,
            "longitude" => $this->longitude,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,
            'total_people_interested' => $this->when(
                $this->whenLoaded('people_interested'),
                $this->people_interested - 1
            ) ?? 0,
            'event_users' => UserBasicInfoResource2::collection($this->first3Attendees),
            'attendees_count' => $this->when(
                $this->resource->relationLoaded('attendees'),
                $attendeesCount
            ),
            'attendees_count_checked_in' => $this->attendees()
                ->whereNotNull('attended_at')
                ->where('user_id', '!=', $this->user_id) // Exclude owner
                ->whereNotIn('user_id', $adminIds) // Exclude admins
                ->count(),
            'attendees_count_invited' => $this->rsvp_type === RSVPType::VIP
                ? $this->attendees()
                ->where('is_vip', 1)
                ->count()
                : null,
            'rsvpd_users_count' => $this->users()
                ->where('user_id', '!=', $this->user_id) // Exclude owner
                ->whereNotIn('user_id', $adminIds) // Exclude admins
                ->count(),
            'rsvpd_users_attending_count' => $this->when(
                $this->rsvp_type === RSVPType::LIMITED ||
                    $this->rsvp_type === RSVPType::VIP,
                $this->usersAttending()
                    ->where('user_id', '!=', $this->user_id) // Exclude owner
                    ->whereNotIn('user_id', $adminIds) // Exclude admins
                    ->where('owner_flagged', 0) // only user who is not flagged
                    ->count()
            ),
            'rsvpd_users_not_invited_count' => $this->when(
                $this->rsvp_type === RSVPType::VIP,
                $this->users()
                    ->where('user_id', '!=', $this->user_id) // Exclude owner
                    ->whereNotIn('user_id', $adminIds) // Exclude admins
                    ->where(function ($query) {
                        $query->whereNull('user_events.qr_code')
                            ->where('owner_flagged', 0);
                    })
                    ->count()
            ),
            'rsvpd_users_waiting_count' => $this->when(
                $this->rsvp_type === RSVPType::LIMITED,
                $this->usersWaiting()
                    ->where('user_id', '!=', $this->user_id) // Exclude owner
                    ->count()
            ),
            'rsvpd_users_flagged_count' => $this->when(
                $this->rsvp_type === RSVPType::LIMITED ||
                    $this->rsvp_type === RSVPType::VIP,
                $this->attendees()
                    ->where('owner_flagged', '=', 1) // Exclude owner
                    ->count()
            ),
            'tags' => $this->when(
                $this->resource->relationLoaded('eventTags'),
                TagResource::collection($this->eventTags)
            ),
            "is_featured" => $this->is_feature ?? 0,
            "is_published" => $this->is_published,
            'new_event_start' => $this->new_event_start,
            'new_event_end' => $this->new_event_end,
            'new_start_time' => $this->new_start_time,
            'new_end_time' => $this->new_end_time,
            'is_past' => $this->isPast,
            'cancelled_at' => $this->cancelled_at,
            "qr_code" => $this->qr_code ? QrCodeRepository::getQrCodeUrl($this->qr_code) : null,
            "is_user_interested" => authCheck() ? $event->isUserInterested($this->id, authUser()->id) : null,
            "is_owner" => authCheck() ? ($this->user_id == authUser()->id ? true : false) : false,
            "is_vip" => $isVip ?? false,
            "ticket_details" => $ticketDetails ?? null,
            "attended_at" => $attendedAt ?? null,
            "is_chat_enabled" => boolval($this->live_chat_enabled),
            "chat_type" => $this->live_chat_type,
            "past_events" => $this->when(
                $this->resource->relationLoaded('eventEmailInvites'),
                EmailInvitesResource::collection($this->eventEmailInvites)
            ),
            "sent_thanks_at" => $this->sent_thanks_at,
            "user_role" => $userRole,
            'gathering_type' => $this->gathering_type,
            "albums_count" => $this->albums_count,
            "photos_count" => $this->photos_count,
            "videos_count" => $this->videos_count,
            "interests" => $eventInterests,
            "status" => $this->status,
            'deleted_at' => $this->deleted_at,
            "user_notes" => $this->when(
                $this->resource->relationLoaded('eventNotes'),
                NoteResource::collection($this->eventNotes)
            ),
            
        ];
    }
}
