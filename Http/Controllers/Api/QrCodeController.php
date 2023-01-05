<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repository\QrCode\QrCodeRepository;
use App\Traits\ApiResponser;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class QrCodeController extends Controller
{
    use ApiResponser;

    /**
     * @param Request $request
     * @param string $code
     *
     * @return mixed
     *
     * @author Richmond De Silva <richmond.ds@ragingriverict.com>
     */
    public function handle(Request $request, string $code)
    {
        if ($request->json === '1') {
            //Log::debug('handling...');
            try {
                $result = QrCodeRepository::getQrCodeJson($code);
                return $this->successResponse(
                    $result
                );
            } catch (Exception $e) {
                return $this->errorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
            }
        }

        // If via browser
        try {
            $redirectLink = QrCodeRepository::getQrCodeRedirectLink($code);
        } catch (\Exception $e) {
            $revokingReason = json_decode($e->getMessage());
            return Inertia::render('ErrorQrCode', [
                'title' => $revokingReason->title,
                'message' => $revokingReason->message,
                'date' => $revokingReason->date,
                'image' => $revokingReason->image,
            ]);
        }

        if ($redirectLink) {
            return redirect($redirectLink);
        }
    }
}
