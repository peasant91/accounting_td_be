<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'currency' => $this->currency,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'subtotal' => (float) $this->subtotal,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'status' => $this->status?->value ?? $this->status,
            'type' => $this->type?->value ?? $this->type,
            'recurring_invoice_id' => $this->recurring_invoice_id,
            'notes' => $this->notes,
            'internal_notes' => $this->internal_notes,
            'cancellation_reason' => $this->cancellation_reason,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'payment_method' => $this->payment_method?->value ?? $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'available_actions' => $this->available_actions,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
