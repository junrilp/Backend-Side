<?php

namespace App\Http\Resources;

use Caseeng\Payment\Base\Models\PaymentTransaction;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class PaymentTransactionResource
 * @package App\Http\Resources
 * @property PaymentTransaction $resource
 */
class PaymentTransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if ($this->resource->amount) {
            $amountFormat = sprintf(
                "%s %s",
                $this->resource->currency,
                number_format((float)$this->resource->amount, 2)
            );
        } else {
            $amountFormat = null;
        }

        return [
            'id' => $this->resource->id,
            'amount' => $this->resource->amount ?: null,
            'amount_format' => $amountFormat,
            'currency' => $this->resource->currency ?: null,
            'success' => $this->resource->success,
            'transaction_date' => $this->resource->transaction_date,
        ];
    }
}
