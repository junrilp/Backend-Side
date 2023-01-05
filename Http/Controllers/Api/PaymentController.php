<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Caseeng\Payment\Base\Common\GatewayInterface;
use Caseeng\Payment\Base\Facades\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Display a available payment methods.
     *
     * @return \Illuminate\Http\Response
     */
    public function paymentMethods()
    {
        $gateways = $this->getPaymentMethods();

        return response()->json([
            'data' => $gateways,
            'success' => true,
        ]);
    }

    /**
     * @return array
     */
    private function getPaymentMethods(): array
    {
        $gateways = Payment::getGateways();
        $nameMap = [
            'paypal-rest' => 'PayPal',
            'stripe-payment-intent' => 'Stripe',
        ];
        return array_map(function (GatewayInterface $gateway) use ($nameMap) {
            $paymentId = $gateway->getIdentifier();
            return [
                'id' => $paymentId,
                'name' => $nameMap[$paymentId] ?? $gateway->getName(),
            ];
        }, array_values($gateways));
    }
}
