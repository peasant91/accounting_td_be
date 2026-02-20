<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoiceControllerTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_mark_as_paid_without_file(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        $invoice = \App\Models\Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-001',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => \App\Enums\InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => \App\Enums\InvoiceType::Manual,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-as-paid", [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertStatus(200);
        $this->assertEquals(\App\Enums\InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->payment_proof_path);
    }

    public function test_mark_as_paid_with_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $customer = \App\Models\Customer::factory()->create();
        $invoice = \App\Models\Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-002',
            'currency' => 'IDR',
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(7)->format('Y-m-d'),
            'status' => \App\Enums\InvoiceStatus::Sent,
            'tax_rate' => 0,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'type' => \App\Enums\InvoiceType::Manual,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('proof.pdf', 1000, 'application/pdf');

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/mark-as-paid", [
            'payment_date' => now()->format('Y-m-d'),
            'payment_method' => 'bank_transfer',
            'payment_proof' => $file,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(\App\Enums\InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->payment_proof_path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($invoice->fresh()->payment_proof_path);
    }
}
