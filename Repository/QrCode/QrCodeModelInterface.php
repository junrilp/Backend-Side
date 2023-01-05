<?php

namespace App\Repository\QrCode;

use Illuminate\Database\Eloquent\Model;

interface QrCodeModelInterface
{
    public function qrCodePrefix(): string;

    public function qrCodeJson(): array;

    public function qrCodeRedirectLink(): ?string;

    public function qrCodeOn(): Model;

    public function qrCodeInvalidMessage(): string;
}
