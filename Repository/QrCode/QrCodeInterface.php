<?php

namespace App\Repository\QrCode;

interface QrCodeInterface
{
    public static function generateQrCodeOn(QrCodeModelInterface $model, string $qrCodeString = null): string;

    public static function getQrCodeUrl(string $qrCode): string;

    public static function getQrCodeJson(string $qrCode): array;

    public static function getQrCodeRedirectLink(string $qrCode): string;

    public static function generateUniqueCode(QrCodeModelInterface $qrCodeModel): string;
}
