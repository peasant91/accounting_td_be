<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->currency,
            'rate_to_base' => (float) $this->rate_to_base,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
