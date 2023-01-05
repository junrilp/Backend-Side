<?php

namespace App\Http\Resources;

use Caseeng\Payment\Base\Models\PaymentSubscription;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class SubscriptionResource
 * @package App\Http\Resources
 * @property PaymentSubscription $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'status' => $this->resource->status,
            'subscription_reference' => $this->resource->uuid,
            'next_billing_schedule' => $this->resource->next_billing_schedule,
            'billing_preferences' => $this->resource->billing_preferences,
            'start_at' => $this->resource->start_at,
            'expire_at' => $this->resource->expire_at,
            'suspended_at' => $this->resource->suspended_at,
            'cancelled_at' => $this->resource->cancelled_at,
            'last_billing_reminder_sent_at' => $this->resource->last_billing_reminder_sent_at,
            'plan' => $this->when(
                $this->resource->relationLoaded('plan'),
                new PlanResource($this->resource->plan)
            ),
            'payment_transactions' => $this->when(
                $this->resource->relationLoaded('paymentTransactions'),
                new PaymentTransactionResourceCollection($this->resource->paymentTransactions)
            ),
        ];
    }
}
