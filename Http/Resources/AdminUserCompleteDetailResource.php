<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Media as MediaResource;
use App\Repository\QrCode\QrCodeRepository;

class AdminUserCompleteDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "first_name" => $this->first_name,
            "last_name" => $this->last_name,
            "user_name" => $this->user_name,
            "email" => $this->email,
            "mobile_number" => $this->mobile_number,
            "email_verified_at" => $this->email_verified_at,
            "account_type" => $this->account_type,
            "primary_photo"=> new MediaResource($this->primaryPhoto),
            "birth_date" => $this->birth_date,
            "zodiac_sign" => $this->zodiac_sign,
            "last_login_at" => $this->last_login_at,
            "qr_code" => $this->qr_code ? QrCodeRepository::getQrCodeUrl($this->qr_code) : null,
            "profile" => $this->profile,
            "photos" => MediaResource::collection($this->photos),
            "interests" => $this->interests,
            "suspended_at" => $this->suspended_at,
            "status" => $this->status,
            'is_case' => $this->is_case,
        ];
    }
}
