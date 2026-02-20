<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'title' => $this->title,
            'recurrence_type' => $this->recurrence_type,
            'recurrence_interval' => $this->recurrence_interval,
            'recurrence_unit' => $this->recurrence_unit,
            'total_count' => $this->total_count,
            'generated_count' => $this->generated_count,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'next_invoice_date' => $this->next_invoice_date?->format('Y-m-d'),
            'status' => $this->status,
            'line_items' => $this->line_items,
            'tax_rate' => (float) $this->tax_rate,
            'currency' => $this->currency,
            'due_date_offset' => $this->due_date_offset,
            'notes' => $this->notes,
            'last_generated_at' => $this->last_generated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
        ];
    }
}
