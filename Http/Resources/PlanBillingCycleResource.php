<?php

namespace App\Http\Resources;

use App\Models\PlanBillingCycle;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class BillingCycleResource
 * @package App\Http\Resources
 * @property PlanBillingCycle $resource
 */
class PlanBillingCycleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'plan_id' => $this->resource->plan_id,
            'price' => $this->resource->price,
            'price_format' => $this->resource->currency . ' ' . number_format($this->resource->price, 2),
            'currency' => $this->resource->currency,
            'frequency_interval_unit' => $this->resource->frequency_interval_unit,
            'frequency_interval_count' => $this->resource->frequency_interval_count,
            'total_cycles' => $this->resource->total_cycles,
            'sequence' => $this->resource->sequence
        ];
    }
}
