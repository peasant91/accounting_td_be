<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer' => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->name,
            ],
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'total' => (float) $this->total,
            'use_unique_code' => (bool) $this->use_unique_code,
            'unique_code' => $this->unique_code,
            'currency' => $this->currency,
            'status' => $this->status?->value ?? $this->status,
            'type' => $this->type?->value ?? $this->type,
        ];
    }
}
