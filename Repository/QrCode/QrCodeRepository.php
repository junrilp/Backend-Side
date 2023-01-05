<?php

namespace App\Repository\QrCode;

use App\Enums\QrCodePrefix;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * QrCodeRepository
 * Set of static methods to generate QR Code and consume it by a given model
 *
 * @author Richmond De Silva <richmond.ds@ragingriverict.com>
 */
class QrCodeRepository implements QrCodeInterface
{
    /**
     * @param QrCodeModelInterface $qrCodeModel
     * @param string|null $qrCodeString
     *
     * @return string
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function generateQrCodeOn(QrCodeModelInterface $qrCodeModel, string $qrCodeString = null): string
    {
        if ($qrCodeString) {
            $uniqueCode = self::replaceIfNotUniqueOn($qrCodeModel, $qrCodeString); 
        } else {
            $uniqueCode = self::generateUniqueCode($qrCodeModel);
        }

        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data(url("api/qr-code/handle/$uniqueCode"))
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(240)
            ->roundBlockSizeMode(new RoundBlockSizeModeNone())
            ->foregroundColor(new Color(33, 33, 33))
            ->logoPath(public_path('/images/logos/perfect-friends-logo-qr-2.png'))
            ->logoResizeToWidth(60)
            // ->labelText($uniqueCode)
            // ->labelFont(new NotoSans(12))
            ->build();

        $image = $result->getImage();

        ob_start();
        imagepng($image);
        $imageContent = ob_get_clean();

        $filename = "qr-code/$uniqueCode.png";

        Storage::put($filename, $imageContent);

        $qrCodeModelModel = $qrCodeModel->qrCodeOn();
        $qrCodeModelModel->qr_code = $uniqueCode;
        $qrCodeModelModel->save();

        return Storage::url($filename);
    }

    /**
     * @param string $qrCode
     *
     * @return string
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    private static function getFilename(string $qrCode): string
    {
        return "qr-code/$qrCode.png";
    }

    /**
     * @param string $qrCode
     *
     * @return string
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function getQrCodePath(string $qrCode): string
    {
        return Storage::path(self::getFilename($qrCode));
    }

    /**
     * @param string $qrCode
     *
     * @return string
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function getQrCodeUrl(string $qrCode): string
    {
        return Storage::url(self::getFilename($qrCode));
    }

    /**
     * @param string $qrCode
     *
     * @return array
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function getQrCodeJson(string $qrCode): array
    {
        return self::getQrCodeModel($qrCode)->qrCodeJson();
    }

    /**
     * @param string $qrCode
     *
     * @return string
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function getQrCodeRedirectLink(string $qrCode): string
    {
        return self::getQrCodeModel($qrCode)->qrCodeRedirectLink();
    }

    /**
     * @param string $qrCode
     *
     * @return QrCodeModelInterface
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    private static function getQrCodeModel(string $qrCode): QrCodeModelInterface
    {
        $prefix = explode('-', $qrCode)[0];
        $className = QrCodePrefix::map()[$prefix];
        $classObject = (new $className);

        try {
            $qrCodeModel = $classObject
                ->where('qr_code', '=', $qrCode)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            $revokingReason = $classObject->qrCodeRevokingReason($qrCode);
            
            $data = [
                'title' => $revokingReason->created_at ? 'Revoked QRCode' : 'Invalid QRCode',
                'image' => $revokingReason->media_id ? Media::find($revokingReason->media_id)->getMediaUrl() : null,
                'message' => $revokingReason->remarks,
                'date' => $revokingReason->created_at ? $revokingReason->created_at->format('F Y g:i A') : null, 
            ];
            throw new \Exception(json_encode($data), Response::HTTP_NOT_FOUND);
        };

        return $qrCodeModel;
    }

    /**
     * @param QrCodeModelInterface $qrCodeModel
     * @param string $uniqueCode
     * 
     * @return string
     */
    private static function replaceIfNotUniqueOn(QrCodeModelInterface $qrCodeModel, string $uniqueCode): string
    {
        if (!self::isReallyUniqueOn($qrCodeModel, $uniqueCode)) {
            return self::generateUniqueCode($qrCodeModel);
        }

        return $uniqueCode;
    }

    /**
     * @param QrCodeModelInterface $qrCodeModel
     * @param string $uniqueCode
     * 
     * @return bool
     */
    private static function isReallyUniqueOn(QrCodeModelInterface $qrCodeModel, string $uniqueCode): bool
    {
        return $qrCodeModel
            ->qrCodeOn()
            ->where('qr_code', '=', $uniqueCode)
            ->doesntExist();
    }

    /**
     * @param QrCodeModelInterface $qrCodeModel
     *
     * @return string
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public static function generateUniqueCode(QrCodeModelInterface $qrCodeModel): string
    {
        /* Generates 10 alphanumeric characters. */
        $bytes = random_bytes(5);
        $uniqueCode = $qrCodeModel->qrCodePrefix() . '-' . strtoupper(bin2hex($bytes));

        /* Make sures that the generated code is not yet used. */
        if (!self::isReallyUniqueOn($qrCodeModel, $uniqueCode)) {
            self::generateUniqueCode($qrCodeModel);
        }

        return $uniqueCode;
    }
}
