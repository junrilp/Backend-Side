<?php

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\PlanBillingCycle;
use Caseeng\Payment\Base\Facades\Subscription as SubscriptionFacade;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;
use Money\Money;

/**
 * Class PlanResource
 * @package App\Http\Resources
 * @property Plan $resource
 */
class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        /** @var Money $setupFee */
        $setupFee = SubscriptionFacade::plans()->getSetupFee($this->resource);
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'description' => $this->resource->description,
            'status' => $this->resource->status,
            'setup_fee' => number_format($setupFee->getAmount() * .01, 2),
            'setup_fee_currency' => $setupFee->getCurrency()->getCode(),
            'summary' => $this->resource->relationLoaded('billingCycles') ? $this->getSummary(): new MissingValue(),
            'billing_cycles' => $this->when(
                $this->resource->relationLoaded('billingCycles'),
                new PlanBillingCycleResourceCollection($this->resource->billingCycles)
            ),
        ];
    }
    
    public function getSummary():?string
    {
       return $this->resource->billingCycles
            ->map(function (PlanBillingCycle $billingCycle) {
                $priceFormat = number_format((float)$billingCycle->price, 2);
                return sprintf(
                    '%s %s every %s %s for %s cycles.',
                    $billingCycle->currency,
                    $priceFormat,
                    $billingCycle->frequency_interval_count,
                    $billingCycle->frequency_interval_unit,
                    $billingCycle->total_cycles === 0 ? 'UNLIMITED' : $billingCycle->total_cycles,
                );
            })
            ->implode('. Then ');
    }
}
