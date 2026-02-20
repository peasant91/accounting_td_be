<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CustomerCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'company_name' => $customer->company_name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'currency' => $customer->currency,
                    'total_receivable' => (float) $customer->total_receivable,
                    'status' => $customer->status?->value ?? $customer->status,
                ];
            }),
        ];
    }
}
